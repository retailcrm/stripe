<?php

namespace App\Tests;

use App\DataFixtures\Test\IntegrationData;
use App\DataFixtures\Test\PaymentData;
use App\Entity\Model\UpdateInvoiceRequest;
use App\Entity\Payment;
use App\Service\StripeManager;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UpdateInvoiceTest extends BaseAppTest
{
    protected function getFixtures()
    {
        return array_merge(
            parent::getFixtures(),
            [
                new IntegrationData(true, 2),
                new PaymentData(true, 1),
            ]
        );
    }

    /**
     * @param $invoiceUuid
     * @param $status
     * @param $cancellationDetails
     * @param $invoiceUrl
     * @param $payedAt
     * @param $expiredAt
     * @param $refundable
     * @param $testResult
     * @dataProvider requestData
     */
    public function testRequestValidation(
        $invoiceUuid,
        $status,
        $cancellationDetails,
        $invoiceUrl,
        $payedAt,
        $expiredAt,
        $refundable,
        $testResult
    ) {
        $container = static::$container;
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findOneBy([]);

        $requestModel = new UpdateInvoiceRequest();
        $requestModel
            ->setInvoiceUuid($invoiceUuid)
            ->setPaymentId($payment->getPaymentUuid())
            ->setStatus($status)
            ->setCancellationDetails($cancellationDetails)
            ->setInvoiceUrl($invoiceUrl)
            ->setPaidAt($payedAt)
            ->setExpiredAt($expiredAt)
            ->setRefundable($refundable)
        ;

        $validator = $container->get(ValidatorInterface::class);
        $errors = $validator->validate($requestModel);

        if ($testResult) {
            $this->assertFalse(count($errors) > 0);
        } else {
            $this->assertTrue(count($errors) > 0);
        }
    }

    public function requestData(): array
    {
        return [
            [Uuid::uuid4(), StripeManager::STATUS_PAYMENT_PENDING, 'details', 'http://stripe.com', new \DateTime(), new \DateTime(), true, true],
            [Uuid::uuid4(), null, null, null, null, null, false, true],
            [null, null, null, null, null, null, false, false],
            ['sdsds', null, null, null, null, null, false, false],
            [Uuid::uuid4(), 'UNKNOW_STATUS', 'details', 'http://stripe.com', new \DateTime(), new \DateTime(), true, true],
            [Uuid::uuid4(), StripeManager::STATUS_PAYMENT_PENDING, 'details', 'not_valid.url', new \DateTime(), new \DateTime(), true, false],
            [Uuid::uuid4(), StripeManager::STATUS_PAYMENT_PENDING, 'details', 'http://stripe.com', new \DateTime(), 'not_date_time', true, false],
            [Uuid::uuid4(), StripeManager::STATUS_PAYMENT_PENDING, 'details', 'http://stripe.com', '2018-10-12 12:12', new \DateTime(), true, false],
        ];
    }
}
