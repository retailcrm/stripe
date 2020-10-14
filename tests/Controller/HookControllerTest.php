<?php

namespace App\Tests\Controller;

use App\DataFixtures\Test\IntegrationData;
use App\DataFixtures\Test\PaymentData;
use App\Entity\Payment;
use App\Entity\StripeNotification;
use App\Service\CRMConnectManager;
use App\Service\StripeManager;
use App\Tests\BaseAppTest;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

class HookControllerTest extends BaseAppTest
{
    protected function getFixtures()
    {
        return array_merge(
            parent::getFixtures(),
            [
                new IntegrationData(true, 2),
                new PaymentData(true, 60),
            ]
        );
    }

    public function testStripeWaitingForCaptureAutomate(): void
    {
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([
            'status' => StripeManager::STATUS_PAYMENT_PENDING,
        ]);
        $payment->getAccount()->setApproveManually(false);
        $em->flush();

        $client = self::getClient();
        $this->setMockInContainer(
            CRMConnectManager::class,
            $client->getContainer(),
            ['updateInvoice' => true, 'checkInvoice' => true]
        );
        $this->requestHook(
            $client,
            $payment,
            'payment_intent.amount_capturable_updated',
            StripeManager::STATUS_PAYMENT_WAITING_CAPTURE,
            true
        );
        $this->checkPaymentAfterHook(
            $client,
            $em,
            $payment,
            StripeManager::STATUS_PAYMENT_SUCCEEDED,
            'payment_intent.amount_capturable_updated'
        );
    }

    public function testStripeWaitingForCaptureManually(): void
    {
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([
            'status' => StripeManager::STATUS_PAYMENT_PENDING,
        ]);
        $payment->getAccount()->setApproveManually(true);
        $em->flush();

        $client = self::getClient();
        $this->setMockInContainer(
            CRMConnectManager::class,
            $client->getContainer(),
            ['updateInvoice' => true, 'checkInvoice' => true]
        );
        $this->requestHook(
            $client,
            $payment,
            'payment_intent.amount_capturable_updated',
            StripeManager::STATUS_PAYMENT_WAITING_CAPTURE,
            true
        );
        $this->checkPaymentAfterHook(
            $client,
            $em,
            $payment,
            StripeManager::STATUS_PAYMENT_WAITING_CAPTURE,
            'payment_intent.amount_capturable_updated'
        );
    }

    public function testStripeWaitingForCaptureBeforeCancel(): void
    {
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([
            'status' => StripeManager::STATUS_PAYMENT_PENDING,
        ]);
        $payment->setCancelOnWaitingCapture(true);
        $em->flush();

        $client = self::getClient();
        $this->requestHook(
            $client,
            $payment,
            'payment_intent.amount_capturable_updated',
            StripeManager::STATUS_PAYMENT_WAITING_CAPTURE,
            true
        );
        $this->checkPaymentAfterHook(
            $client,
            $em,
            $payment,
            StripeManager::STATUS_PAYMENT_CANCELED,
            'payment_intent.amount_capturable_updated'
        );
    }

    public function testStripeCanceled(): void
    {
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([
            'status' => StripeManager::STATUS_PAYMENT_PENDING,
        ]);

        $client = self::getClient();
        $this->requestHook(
            $client,
            $payment,
            'payment_intent.canceled',
            StripeManager::STATUS_PAYMENT_CANCELED
        );
        $this->checkPaymentAfterHook(
            $client,
            $em,
            $payment,
            StripeManager::STATUS_PAYMENT_CANCELED,
            'payment_intent.canceled'
        );
    }

    public function testStripeSucceededAmountCompare(): void
    {
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([
            'status' => StripeManager::STATUS_PAYMENT_WAITING_CAPTURE,
        ]);

        $client = self::getClient();
        $this->setMockInContainer(
            CRMConnectManager::class,
            $client->getContainer(),
            ['updateInvoice' => true]
        );
        $this->requestHook(
            $client,
            $payment,
            'payment_intent.succeeded',
            StripeManager::STATUS_PAYMENT_SUCCEEDED,
            true,
            10
        );
        $this->checkPaymentAfterHook(
            $client,
            $em,
            $payment,
            StripeManager::STATUS_PAYMENT_SUCCEEDED,
            'payment_intent.succeeded',
            10
        );
    }

    public function testStripeSucceededAmountGreater(): void
    {
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([
            'status' => StripeManager::STATUS_PAYMENT_WAITING_CAPTURE,
        ]);

        $client = self::getClient();
        $this->setMockInContainer(
            CRMConnectManager::class,
            $client->getContainer(),
            ['updateInvoice' => true]
        );
        $this->requestHook(
            $client,
            $payment,
            'payment_intent.succeeded',
            StripeManager::STATUS_PAYMENT_SUCCEEDED,
            true,
            10.1
        );
        $this->checkPaymentAfterHook(
            $client,
            $em,
            $payment,
            StripeManager::STATUS_PAYMENT_SUCCEEDED,
            'payment_intent.succeeded',
            10.1
        );
    }

    public function testStripeSucceededAmountLess(): void
    {
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([
            'status' => StripeManager::STATUS_PAYMENT_WAITING_CAPTURE,
        ]);

        $client = self::getClient();
        $this->setMockInContainer(
            CRMConnectManager::class,
            $client->getContainer(),
            ['updateInvoice' => true]
        );
        $this->requestHook(
            $client,
            $payment,
            'payment_intent.succeeded',
            StripeManager::STATUS_PAYMENT_SUCCEEDED,
            true,
            9.9
        );
        $this->checkPaymentAfterHook(
            $client,
            $em,
            $payment,
            StripeManager::STATUS_PAYMENT_SUCCEEDED,
            'payment_intent.succeeded',
            9.9
        );
    }

    public function testStripeRefundCompare(): void
    {
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([
            'status' => StripeManager::STATUS_PAYMENT_SUCCEEDED,
        ]);

        $client = self::getClient();
        $this->requestHookRefund(
            $client,
            $payment,
            'charge.refunded',
            StripeManager::STATUS_PAYMENT_SUCCEEDED,
            true,
            10
        );
        $this->checkPaymentAfterHook(
            $client,
            $em,
            $payment,
            StripeManager::STATUS_PAYMENT_REFUND_SUCCEEDED,
            'charge.refunded',
            10,
            0
        );
    }

    public function testStripeRefundGreater(): void
    {
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([
            'status' => StripeManager::STATUS_PAYMENT_SUCCEEDED,
        ]);

        $client = self::getClient();
        $this->requestHookRefund(
            $client,
            $payment,
            'charge.refunded',
            StripeManager::STATUS_PAYMENT_SUCCEEDED,
            true,
            10.1
        );
        $this->checkPaymentAfterHook(
            $client,
            $em,
            $payment,
            StripeManager::STATUS_PAYMENT_REFUND_SUCCEEDED,
            'charge.refunded',
            10,
            10.1
        );
    }

    public function testStripeRefundLess(): void
    {
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([
            'status' => StripeManager::STATUS_PAYMENT_SUCCEEDED,
        ]);

        $client = self::getClient();
        $this->requestHookRefund(
            $client,
            $payment,
            'charge.refunded',
            StripeManager::STATUS_PAYMENT_SUCCEEDED,
            true,
            8.5
        );
        $this->checkPaymentAfterHook(
            $client,
            $em,
            $payment,
            StripeManager::STATUS_PAYMENT_SUCCEEDED,
            'charge.refunded',
            10,
            8.5
        );

        $this->requestHookRefund(
            $client,
            $payment,
            'charge.refunded',
            StripeManager::STATUS_PAYMENT_SUCCEEDED,
            true,
            1.5
        );

        $this->checkPaymentAfterHook(
            $client,
            $em,
            $payment,
            StripeManager::STATUS_PAYMENT_REFUND_SUCCEEDED,
            'charge.refunded',
            10,
            10
        );
    }

    public function hookStatuses(): array
    {
        return [
            [StripeManager::STATUS_PAYMENT_PENDING, 'payment_intent.amount_capturable_updated'],
            [StripeManager::STATUS_PAYMENT_WAITING_CAPTURE, 'payment_intent.succeeded'],
            [StripeManager::STATUS_PAYMENT_PENDING, 'payment_intent.canceled'],
        ];
    }

    /**
     * @dataProvider hookStatuses
     *
     * Тест проверяет что чужие хуки не влияют на платеж
     */
    public function testForeignHooks($status, $event)
    {
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([
            'status' => $status,
        ]);

        $client = self::getClient();
        $url = $client->getContainer()->get('router')->generate(
            'stripe_hooks',
            ['id' => Uuid::uuid4()->toString()]
        );

        $content = json_encode([
            'id' => 'evt_1HYZtWIpoH9U2y2v1QeGdjtq',
            'object' => 'event',
            'api_version' => '2020-03-02',
            'created' => '1601828226',
            'data' => [
                'object' => [
                    'id' => $payment->getId(),
                    'object' => 'payment_intent',
                    'status' => $status,
                    'amount' => $payment->getAmount() * 100,
                    'currency' => mb_strtolower($payment->getCurrency()),
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
            ],
            'livemode' => 1,
            'pending_webhooks' => 0,
            'request' => [
                'id' => 'req_0J1x7qMn4CjeHV',
                'idempotency_key' => '',
            ],
            'type' => $event,
        ], JSON_THROW_ON_ERROR, 512);

        $client->request(
            Request::METHOD_POST,
            $url,
            [],
            [],
            [],
            $content
        );

        self::assertTrue($client->getResponse()->isNotFound());

        $em->close();
        $payment = $em->getRepository(Payment::class)->find($payment->getId());

        $this->assertEquals($status, $payment->getStatus());
    }

    private function requestHook(
        KernelBrowser $client,
        Payment $payment,
        string $event,
        string $status,
        bool $paid = false,
        string $amount = null
    ): void {
        $url = $client->getContainer()->get('router')->generate(
            'stripe_hooks',
            ['id' => $payment->getAccount()->getId()]
        );

        $content = json_encode([
            'id' => 'evt_1HYZtWIpoH9U2y2v1QeGdjtq',
            'object' => 'event',
            'api_version' => '2020-03-02',
            'created' => '1601828226',
            'data' => [
                'object' => [
                    'id' => $payment->getId(),
                    'object' => 'payment_intent',
                    'status' => $status,
                    'amount' => $amount ? $amount * 100 : $payment->getAmount() * 100,
                    'currency' => mb_strtolower($payment->getCurrency()),
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
                                'paid' => $paid,
                                'refunded' => false,
                            ],
                        ],
                    ],
                ],
            ],
            'livemode' => 1,
            'pending_webhooks' => 0,
            'request' => [
                'id' => 'req_0J1x7qMn4CjeHV',
                'idempotency_key' => '',
            ],
            'type' => $event,
        ], JSON_THROW_ON_ERROR, 512);

        $client->request(
            Request::METHOD_POST,
            $url,
            [],
            [],
            [],
            $content
        );

        self::assertResponseIsSuccessful();
    }

    /**
     * @throws \Exception
     */
    private function requestHookRefund(
        KernelBrowser $client,
        Payment $payment,
        string $event,
        string $status,
        bool $paid = false,
        string $amount = null
    ): void {
        $url = $client->getContainer()->get('router')->generate(
            'stripe_hooks',
            ['id' => $payment->getAccount()->getId()]
        );

        $content = json_encode([
            'id' => 'evt_1HYqy3IpoH9U2y2vGLFNoHX5',
            'object' => 'event',
            'api_version' => '2020-03-02',
            'created' => '1601893855',
            'data' => [
                'object' => [
                    'id' => 'ch_1HYqwMIpoH9U2y2vAIRNbbM4',
                    'object' => 'charge',
                    'payment_intent' => $payment->getId(),
                    'paid' => $paid,
                    'refunds' => [
                        'object' => 'list',
                        'data' => [
                            [
                                'id' => 're_' . Uuid::uuid4()->toString(),
                                'object' => 'refund',
                                'created' => '1601893855',
                                'amount' => $amount ? $amount * 100 : $payment->getAmount() * 100,
                                'currency' => mb_strtolower($payment->getCurrency()),
                                'payment_intent' => $payment->getId(),
                                'reason' => 'requested_by_customer',
                                'status' => 'succeeded',
                            ],
                        ],
                    ],
                ],
            ],
            'livemode' => 1,
            'pending_webhooks' => 0,
            'request' => [
                'id' => 'req_0J1x7qMn4CjeHV',
                'idempotency_key' => '',
            ],
            'type' => $event,
        ], JSON_THROW_ON_ERROR, 512);

        $client->request(
            Request::METHOD_POST,
            $url,
            [],
            [],
            [],
            $content
        );

        self::assertResponseIsSuccessful();
    }

    private function checkPaymentAfterHook(
        KernelBrowser $client,
        EntityManagerInterface $em,
        Payment $payment,
        string $paymentStatus,
        string $notificationType,
        string $amount = null,
        string $refundAmount = null
    ): void {
        $em->clear();

        $payment = $em->getRepository(Payment::class)->find($payment->getId());

        $notification = $em->getRepository(StripeNotification::class)->findOneBy([
            'payment' => $payment,
            'event' => $notificationType,
        ]);

        $responseStatusCode = $client->getResponse()->getStatusCode();

        $this->assertEquals(200, $responseStatusCode);
        $this->assertNotNull($notification);
        $this->assertEquals($paymentStatus, $payment->getStatus());

        if ($amount) {
            $this->assertEquals(0, bccomp($amount, $payment->getAmount(), 2));
        }

        if ($refundAmount) {
            $this->assertEquals(0, bccomp($refundAmount, $payment->getRefundedAmount(), 2));
        }
    }
}
