<?php

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\Integration;
use App\Entity\Payment;
use App\Entity\Refund;
use App\Entity\Url;
use App\Service\CRMConnectManager;
use App\Service\StripeManager;
use App\Tests\BaseAppTest;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CRMConnectManagerTest extends BaseAppTest
{
    public function testCreateUpdateInvoiceRequest(): void
    {
        self::bootKernel();

        $em = self::$container->get(EntityManagerInterface::class);
        $crmConnectManager = self::$container->get(CRMConnectManager::class);

        [
            $invoiceUuid,
            $paymentUuid,
            $refundUuid,
            $refundUuidLast
        ] = [Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4()];

        $integration = new Integration();
        $integration
            ->setCrmApiKey('testApiKey')
            ->setCrmUrl('test.local');

        $em->persist($integration);

        $slug = 'test11';
        $payUrl = self::$container->get('router')->generate('short_url', [
            'slug' => $slug,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $account = new Account();
        $account
            ->setIntegration($integration)
            ->setAccountId('acct_00000')
            ->setPublicKey('pk_test_000000000000000000')
            ->setSecretKey('sk_test_000000000000000000')
            ->setName('Account')
        ;
        $em->persist($account);

        $url = new Url();
        $url->setSlug($slug);
        $em->persist($url);

        $payment = new Payment($account);
        $payment
            ->setAmount('20.55')
            ->setRefundedAmount('10')
            ->setId('pi_' . Uuid::uuid4()->toString())
            ->setStatus(StripeManager::STATUS_PAYMENT_REFUND_SUCCEEDED)
            ->setInvoiceUuid($invoiceUuid)
            ->setPaymentUuid($paymentUuid)
            ->setUrl($url)
            ->setCapturedAt(new \DateTime('2019-08-01 11:11:11'))
            ->setExpiresAt(new \DateTime('2019-08-01 12:12:12'))
            ->setSessionId('cs_test_0')
            ->setRefundable(true);

        $em->persist($payment);

        $refund = new Refund();
        $refund
            ->setId('re_' . $refundUuid)
            ->setStatus(Refund::STATUS_SUCCEEDED)
            ->setCurrency('RUB')
            ->setAmount('10')
            ->setCreatedAt(new \DateTime('2019-08-01 13:13:12'));
        $payment->addRefund($refund);
        $em->persist($refund);

        $refundLast = new Refund();
        $refundLast
            ->setId('re_' . $refundUuidLast)
            ->setStatus(Refund::STATUS_SUCCEEDED)
            ->setCurrency('RUB')
            ->setAmount('10.55')
            ->setCreatedAt(new \DateTime('2019-08-01 13:13:13'));
        $payment->addRefund($refundLast);
        $em->persist($refundLast);

        $em->flush();
        $em->clear();

        $payment = $em->getRepository(Payment::class)->find($payment->getId());

        $result = $this->callMethod($crmConnectManager, 'createUpdateInvoiceRequest', [
            'payment' => $payment,
            'withStatus' => true,
            'refund' => $refundLast,
        ]);

        $this->assertEquals([
            'invoiceUuid' => $invoiceUuid->toString(),
            'paymentId' => $paymentUuid->toString(),
            'status' => 'refundSucceeded',
            'invoiceUrl' => $payUrl,
            'paidAt' => '2019-08-01 11:11:11',
            'expiredAt' => '2019-08-01 12:12:12',
            'refundable' => true,
            'amount' => 20.55,
            'cancellable' => true,
            'refund' => [
                'id' => 're_' . $refundUuidLast->toString(),
                'status' => 'succeeded',
                'amount' => 10.55,
            ],
        ], $result);
    }

    /**
     * @param mixed $object
     *
     * @return mixed
     *
     * @throws \ReflectionException
     */
    private function callMethod($object, string $name, array $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($name);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}
