<?php

namespace App\Tests;

use App\DataFixtures\Test\IntegrationData;
use App\DataFixtures\Test\PaymentData;
use App\Entity\Account;
use App\Entity\Payment;
use App\Entity\Refund;
use App\Service\StripeManager;
use Doctrine\ORM\EntityManagerInterface;

class PaymentAPITest extends BaseAppTest
{
    protected function getFixtures()
    {
        return array_merge(
            [
                new IntegrationData(true, 1),
                new PaymentData(true, 4),
            ]
        );
    }

    public function testCreatePayment()
    {
        $container = self::$container;
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var Account $account */
        $account = $em->getRepository(Account::class)->findOneBy([]);

        $client = self::getClient();

        $params = RequestPaymentAPIHelper::createPaymentParams($account->getIntegration()->getSlug(), $account->getId());

        $client->request('POST', '/payment/create', $params);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertNotEmpty($response['result']['invoiceUrl']);
        $this->assertNotEmpty($response['result']['paymentId']);
        $payment = $em->getRepository(Payment::class)->findByPaymentUuid($response['result']['paymentId']);
        $this->assertNotNull($payment);
        $this->assertEquals(StripeManager::STATUS_PAYMENT_PENDING, $payment->getStatus());

        $params = RequestPaymentAPIHelper::createEmptyPaymentParams($account->getIntegration()->getSlug());
        $client->request('POST', '/payment/create', $params);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertNotEmpty($response['errorMsg']);
    }

    public function testCancelPayment()
    {
        $container = self::$container;
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([]);

        $client = self::getClient();

        $params = RequestPaymentAPIHelper::createCancelParams(
            $payment->getAccount()->getIntegration()->getSlug(),
            $payment->getPaymentUuid()
        );

        $client->request('POST', '/payment/cancel', $params);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);

        $params = RequestPaymentAPIHelper::createBadCancelParamsArray(
            $payment->getAccount()->getIntegration()->getSlug(),
            $payment->getPaymentUuid()
        );
        foreach ($params as $param) {
            $client->request('POST', '/payment/cancel', $param);
            $response = json_decode($client->getResponse()->getContent(), true);
            $this->assertFalse($response['success']);
            $this->assertNotEmpty($response['errorMsg']);
        }
    }

    public function testApprovePayment()
    {
        $container = self::$container;
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([]);
        $payment->setStatus(StripeManager::STATUS_PAYMENT_WAITING_CAPTURE);
        $payment->getAccount()->setApproveManually(true); // Чтобы успешно пройти метод подтверждения оплаты
        $em->flush();

        $client = self::getClient();

        $params = RequestPaymentAPIHelper::createApproveParams(
            $payment->getAccount()->getIntegration()->getSlug(),
            $payment->getPaymentUuid()
        );

        $client->request('POST', '/payment/approve', $params);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $payment = $em->getRepository(Payment::class)->find($payment->getId());
        $this->assertEquals(StripeManager::STATUS_PAYMENT_SUCCEEDED, $payment->getStatus());

        $params = RequestPaymentAPIHelper::createBadApproveParamsArray(
            $payment->getAccount()->getIntegration()->getSlug(),
            $payment->getPaymentUuid()
        );
        foreach ($params as $param) {
            $client->request('POST', '/payment/approve', $param);
            $response = json_decode($client->getResponse()->getContent(), true);
            $this->assertFalse($response['success']);
            $this->assertNotEmpty($response['errorMsg']);
        }
    }

    public function testStatusPayment()
    {
        $container = self::$container;
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([]);

        $client = self::getClient();

        $params = RequestPaymentAPIHelper::createStatusParams(
            $payment->getAccount()->getIntegration()->getSlug(),
            $payment->getPaymentUuid()
        );

        $client->request('GET', '/payment/status', $params);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertNotEmpty($response['result']['status']);
        $this->assertEquals($payment->getStatus(), $response['result']['status']);

        $keyExists = ['cancellationDetails', 'expiredAt', 'paidAt', 'paymentId', 'invoiceUrl', 'refundable'];
        foreach ($keyExists as $key) {
            $this->assertArrayHasKey($key, $response['result']);
        }

        $params = RequestPaymentAPIHelper::createBadStatusParamsArray(
            $payment->getAccount()->getIntegration()->getSlug(),
            $payment->getPaymentUuid()
        );
        foreach ($params as $param) {
            $client->request('GET', '/payment/status', $param);
            $response = json_decode($client->getResponse()->getContent(), true);
            $this->assertFalse($response['success']);
            $this->assertNotEmpty($response['errorMsg']);
        }
    }

    public function testRefundPayment()
    {
        $container = self::$container;
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([]);
        $payment->setStatus(StripeManager::STATUS_PAYMENT_SUCCEEDED);
        $em->flush();

        $paymentId = $payment->getPaymentUuid();
        $clientId = $payment->getAccount()->getIntegration()->getSlug();

        $params = RequestPaymentAPIHelper::createRefundParams($clientId, $paymentId);

        $client = self::getClient();
        $client->request('POST', '/payment/refund', $params);
        $response = json_decode($client->getResponse()->getContent(), true);

        $this->checkRefundResponse($response, $payment->getAmount());
        $payment = $em->getRepository(Payment::class)->find($payment->getId());
        $this->assertEquals(StripeManager::STATUS_PAYMENT_REFUND_SUCCEEDED, $payment->getStatus());

        $payment
            ->setStatus(StripeManager::STATUS_PAYMENT_SUCCEEDED)
            ->setRefundedAmount(0)
        ;
        $em->flush();

        // Возврат с указанием суммы, возвращаем полную сумму
        $amount = $payment->getAmount();
        $params = RequestPaymentAPIHelper::createRefundParamsWithAmount($clientId, $paymentId, $amount);
        $client->request('POST', '/payment/refund', $params);
        $response = json_decode($client->getResponse()->getContent(), true);
        $refund = $this->checkRefundResponse($response, $amount);
        $em->clear();
        $payment = $em->getRepository(Payment::class)->find($payment->getId());
        $this->assertEquals(StripeManager::STATUS_PAYMENT_REFUND_SUCCEEDED, $payment->getStatus());
        $this->assertEquals(0, bccomp($amount, $refund->getAmount(), 2));

        //Статус != succeeded
        $params = RequestPaymentAPIHelper::createRefundParams($clientId, $paymentId);
        $client->request('POST', '/payment/refund', $params);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);

        //Неверные параметры запроса
        $params = RequestPaymentAPIHelper::createBadRefundParamsArray($clientId, $paymentId);
        foreach ($params as $param) {
            $client->request('POST', '/payment/refund', $param);
            $response = json_decode($client->getResponse()->getContent(), true);
            $this->assertFalse($response['success']);
            $this->assertNotEmpty($response['errorMsg']);
        }
    }

    public function testNotFullRefund()
    {
        $container = self::$container;
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([]);
        $payment->setStatus(StripeManager::STATUS_PAYMENT_SUCCEEDED);
        $em->flush();

        $paymentId = $payment->getPaymentUuid();
        $clientId = $payment->getAccount()->getIntegration()->getSlug();

        // Возврат неполной суммы, статус не меняется в refund.succeeded, остается в succeeded
        $amount = '1.1';
        $client = self::getClient();
        $params = RequestPaymentAPIHelper::createRefundParamsWithAmount($clientId, $paymentId, $amount);
        $client->request('POST', '/payment/refund', $params);
        $response = json_decode($client->getResponse()->getContent(), true);
        $refund = $this->checkRefundResponse($response, $amount);
        $em->clear();
        $payment = $em->getRepository(Payment::class)->find($payment->getId());
        $this->assertEquals(StripeManager::STATUS_PAYMENT_SUCCEEDED, $payment->getStatus());
        $this->assertEquals(0, bccomp($amount, $refund->getAmount(), 2));

        // Валидация суммы возврата
        $amountValues = ['0', 'sds', -1, 0];
        foreach ($amountValues as $amount) {
            $params = RequestPaymentAPIHelper::createRefundParamsWithAmount($clientId, $paymentId, $amount);
            $client->request('POST', '/payment/refund', $params);
            $response = json_decode($client->getResponse()->getContent(), true);
            $this->assertFalse($response['success']);
            $this->assertNotEmpty($response['errorMsg']);
        }
    }

    private function checkRefundResponse(array $response, $amount): Refund
    {
        $this->assertTrue($response['success']);
        $this->assertNotEmpty($response['result']['status']);
        $this->assertNotEmpty($response['result']['id']);
        $this->assertNotEmpty($response['result']['amount']);
        $this->assertEquals(0, bccomp($amount, $response['result']['amount'], 2));
        /** @var Refund $refund */
        $refund = self::$container
            ->get(EntityManagerInterface::class)
            ->getRepository(Refund::class)
            ->find($response['result']['id'])
        ;
        $this->assertNotNull($refund);

        return $refund;
    }
}
