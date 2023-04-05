<?php

namespace App\Tests\Controller;

use App\DataFixtures\Test\IntegrationData;
use App\DataFixtures\Test\PaymentData;
use App\Entity\Integration;
use App\Entity\Payment;
use App\Service\CRMConnectManager;
use App\Service\StripeManager;
use App\Tests\BaseAppTest;
use App\Tests\RequestPaymentAPIHelper;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

class IntegrationEnableTest extends BaseAppTest
{
    const EVENT_STATUS_MAP = [
        'payment_intent.amount_capturable_updated' => StripeManager::STATUS_PAYMENT_WAITING_CAPTURE,
        'payment_intent.canceled' => StripeManager::STATUS_PAYMENT_CANCELED,
        'payment_intent.succeeded' => StripeManager::STATUS_PAYMENT_SUCCEEDED,
        'charge.refunded' => StripeManager::STATUS_PAYMENT_REFUND_SUCCEEDED,
    ];

    protected function getFixtures()
    {
        return array_merge(
            [
                new IntegrationData(true, 1),
                new PaymentData(true, 4),
            ]
        );
    }

    public function testPaymentActiveIntegration()
    {
        $container = self::$container;
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([]);
        $account = $payment->getAccount();
        $integration = $account->getIntegration();
        $integration->setActive(true)->setFreeze(false);
        $account->setApproveManually(true); // Чтобы успешно пройти метод подтверждения оплаты
        $em->flush();

        $client = self::getClient();
        $endpoints = [
            '/payment/create' => [
                'method' => 'POST',
                'params' => RequestPaymentAPIHelper::createPaymentParams($integration->getSlug(), $account->getId()),
            ],
            '/payment/cancel' => [
                'method' => 'POST',
                'params' => RequestPaymentAPIHelper::createCancelParams($integration->getSlug(), $payment->getPaymentUuid()),
            ],
            '/payment/approve' => [
                'method' => 'POST',
                'params' => RequestPaymentAPIHelper::createApproveParams($integration->getSlug(), $payment->getPaymentUuid()),
                'paymentId' => $payment->getId(),
            ],
            '/payment/status' => [
                'method' => 'GET',
                'params' => RequestPaymentAPIHelper::createStatusParams($integration->getSlug(), $payment->getPaymentUuid()),
            ],
            '/payment/refund' => [
                'method' => 'POST',
                'params' => RequestPaymentAPIHelper::createRefundParams($integration->getSlug(), $payment->getPaymentUuid()),
            ],
        ];

        $this->checkEndpoints($client, $endpoints, true);

        /** @var Integration $integration */
        $integration = $em->getRepository(Integration::class)->findOneBy([]);
        $integration->setActive(false)->setFreeze(false);
        $em->flush();
        $this->checkEndpoints($client, $endpoints, false);

        $integration = $em->getRepository(Integration::class)->findOneBy([]);
        $integration->setActive(true)->setFreeze(true);
        $em->flush();
        $this->checkEndpoints($client, $endpoints, false);

        $integration = $em->getRepository(Integration::class)->findOneBy([]);
        $integration->setActive(false)->setFreeze(true);
        $em->flush();
        $this->checkEndpoints($client, $endpoints, false);
    }

    public function hookStatuses()
    {
        return [
            [StripeManager::STATUS_PAYMENT_PENDING, 'payment_intent.amount_capturable_updated'],
            [StripeManager::STATUS_PAYMENT_WAITING_CAPTURE, 'payment_intent.succeeded'],
            [StripeManager::STATUS_PAYMENT_PENDING, 'payment_intent.canceled'],
            [StripeManager::STATUS_PAYMENT_SUCCEEDED, 'charge.refunded'],
        ];
    }

    /**
     * @dataProvider hookStatuses
     */
    public function testStripeHookActiveIntegration($defaultPaymentStatus, $notificationStatus)
    {
        $container = self::$container;
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy(['status' => $defaultPaymentStatus]);
        $payment->getAccount()->setApproveManually(true);
        $em->flush();

        $integration = $payment->getAccount()->getIntegration();

        $this->checkStripeHook($payment, $notificationStatus);

        $integration->setActive(false)->setFreeze(false);
        $em->flush();
        $this->checkStripeHook($payment, $notificationStatus);

        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->find($payment->getId());
        $integration->setActive(true)->setFreeze(true);
        $em->flush();
        $this->checkStripeHook($payment, $notificationStatus);

        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->find($payment->getId());
        $integration->setActive(false)->setFreeze(true);
        $em->flush();
        $this->checkStripeHook($payment, $notificationStatus);
    }

    private function checkStripeHook(Payment $payment, $notificationStatus)
    {
        $content = json_encode([
            'id' => 'evt_1HYZtWIpoH9U2y2v1QeGdjtq',
            'object' => 'event',
            'api_version' => '2020-03-02',
            'created' => '1601828226',
            'data' => $this->getRequestNotificationObject($payment, self::EVENT_STATUS_MAP[$notificationStatus]),
            'livemode' => 1,
            'pending_webhooks' => 0,
            'request' => [
                'id' => 'req_0J1x7qMn4CjeHV',
                'idempotency_key' => '',
            ],
            'type' => $notificationStatus,
        ]);

        $client = self::getClient();
        $this->setMockInContainer(
            CRMConnectManager::class,
            $client->getContainer(),
            ['checkInvoice' => true, 'updateInvoice' => true]
        );
        $client->disableReboot();

        $url = self::$container->get('router')->generate(
            'stripe_hooks',
            ['id' => $payment->getAccount()->getId()]
        );
        $client->request(
            'POST',
            $url,
            [],
            [],
            [],
            $content
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $em = self::$container->get(EntityManagerInterface::class);
        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->find($payment->getId());
        $this->assertArrayHasKey($notificationStatus, self::EVENT_STATUS_MAP);
        $this->assertEquals(self::EVENT_STATUS_MAP[$notificationStatus], $payment->getStatus());
    }

    private function checkEndpoints($client, $endpoints, bool $expectResult)
    {
        foreach ($endpoints as $endpoint => $data) {
            if (!empty($data['paymentId'])) { // Изменяем статус оплаты, чтобы конкретный метод смог успешно выполниться
                $this->updatePaymentStatus($data['paymentId'], StripeManager::STATUS_PAYMENT_WAITING_CAPTURE);
            }
            $client->request($data['method'], $endpoint, $data['params']);
            $response = json_decode($client->getResponse()->getContent(), true);
            if ($expectResult) {
                $this->assertTrue($response['success']);
            } else {
                $this->assertFalse($response['success']);
            }
        }
    }

    private function updatePaymentStatus($id, $status)
    {
        $container = self::$container;
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->find($id);
        $payment->setStatus($status);
        $em->flush();
    }

    private function getRequestNotificationObject(Payment $payment, $status)
    {
        if (StripeManager::STATUS_PAYMENT_REFUND_SUCCEEDED === $status) {
            return [
                'object' => [
                    'id' => 'ch_1HYqwMIpoH9U2y2vAIRNbbM4',
                    'object' => 'charge',
                    'payment_intent' => $payment->getIntentId(),
                    'paid' => true,
                    'refunds' => [
                        'object' => 'list',
                        'data' => [
                            [
                                'id' => 're_' . Uuid::uuid4()->toString(),
                                'object' => 'refund',
                                'created' => '1601893855',
                                'amount' => '1000',
                                'currency' => 'rub',
                                'payment_intent' => $payment->getIntentId(),
                                'reason' => 'requested_by_customer',
                                'status' => 'succeeded',
                            ],
                        ],
                    ],
                ],
            ];
        }

        return [
            'object' => [
                'id' => $payment->getIntentId(),
                'object' => 'payment_intent',
                'status' => $status,
                'amount' => '1000',
                'currency' => 'rub',
                'created' => '1601828099',
                'cancellation_reason' => '',
                'metadata' => [
                    'invoiceUuid' => $payment->getInvoiceUuid(),
                ],
                'charges' => [
                    'object' => 'list',
                    'data' => [
                        [
                            'id' => 'ch_1HYZryIpoH9U2y2vJpyXHe9y',
                            'object' => 'charge',
                            'created' => '1601828130',
                            'paid' => true,
                            'refunded' => false,
                        ],
                    ],
                ],
            ],
        ];
    }
}
