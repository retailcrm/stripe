<?php

namespace App\DataFixtures\Test;

use App\Entity\Account;
use App\Entity\Payment;
use App\Entity\Url;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

class PaymentData extends AbstractTestFixture
{
    protected function getTableNames(ObjectManager $em)
    {
        return [
            $em->getClassMetadata(Payment::class)->getTableName(),
            $em->getClassMetadata(Url::class)->getTableName(),
        ];
    }

    public function load(ObjectManager $em)
    {
        $statuses = [
            'requires_payment_method',
            'requires_capture',
            'succeeded',
            'canceled',
            'refund.succeeded',
        ];
        $currencies = [
            'USD',
            'EUR',
            'RUB',
            'CLP',
            'COP',
            'ARS',
            'PEN',
            'MXN',
        ];

        $accounts = $em->getRepository(Account::class)->findAll();
        for ($i = 0; $i < $this->count; ++$i) {
            $payment = new Payment($accounts[$i % count($accounts)]);
            $payment
                ->setId('pi_' . $i)
                ->setStatus($statuses[$i % count($statuses)])
                ->setPaid(false)
                ->setAmount('10.00')
                ->setCurrency($currencies[$i % count($currencies)])
                ->setCreatedAt(new \DateTime())
                ->setExpiresAt(null)
                ->setTest(false)
                ->setInvoiceUuid(Uuid::uuid4())
                ->setPaymentUuid(Uuid::uuid4())
                ->setSessionId('cs_test_' . $i)
            ;

            if ('succeeded' === $payment->getStatus()) {
                $payment
                    ->setPaid(true)
                    ->setCapturedAt(new \DateTime())
                ;
            }
            if ('requires_capture' === $payment->getStatus()) {
                $payment
                    ->setExpiresAt(new \DateTime())
                ;
            }

            $em->persist($payment);

            $url = new Url();
            $url->setSlug(str_pad($i, 6, '0', STR_PAD_LEFT))
                ->setRequest([])
                ->setAccount($accounts[$i % count($accounts)]);

            $em->persist($url);

            $payment->setUrl($url);
        }

        $em->flush();
    }
}
