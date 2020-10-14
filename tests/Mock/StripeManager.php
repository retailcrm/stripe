<?php

namespace App\Tests\Mock;

use App\Entity\Account;
use App\Entity\Payment;
use App\Entity\PaymentAPIModel\CreatePayment;
use App\Entity\Refund;
use App\Service\StripeManager as BaseStripeManager;
use Ramsey\Uuid\Uuid;
use Stripe\StripeClient;

//use YandexCheckout\Client;
//use YandexCheckout\Model\PaymentInterface;
//use YandexCheckout\Request\Payments\CreatePaymentRequest;
//use YandexCheckout\Request\Payments\PaymentResponse;
//use YandexCheckout\Request\Refunds\RefundResponse;

class StripeManager extends BaseStripeManager
{
    public function getAccountInfo(Account $account)
    {
        return [
            'id' => 'acct_0000000000000',
            'object' => 'account',
            'business_profile' => [],
            'capabilities' => [],
            'charges_enabled' => true,
            'country' => 'US',
            'default_currency' => 'usd',
            'details_submitted' => '',
            'email' => 'test@gmail.com',
            'payouts_enabled' => false,
            'settings' => [
                'branding' => [
                    'icon' => '',
                    'logo' => '',
                    'primary_color' => '',
                    'secondary_color' => '',
                ],
                'card_payments' => [
                    'statement_descriptor_prefix' => '',
                ],
                'dashboard' => [
                    'display_name' => 'Test Store',
                    'timezone' => 'Europe/Moscow',
                ],
                'payments' => [
                    'statement_descriptor' => '',
                    'statement_descriptor_kana' => '',
                    'statement_descriptor_kanji' => '',
                ],
            ],
            'type' => 'standard',
        ];
    }

    /**
     * @throws \Exception
     */
    public function createPayment(
        Account $account,
        CreatePayment $createPayment,
        string $paymentUuid
    ): Payment {
        $fp = fopen('/var/www/stripe/log.txt', 'a+');
        fwrite($fp, "MOCK StripeManager::createPayment\n");

        $payment = new Payment($account);
        $payment
            ->setId('pi_' . Uuid::uuid4()->toString())
            ->setStatus(self::STATUS_PAYMENT_PENDING)
            ->setPaid(false)
            ->setAmount($createPayment->getAmount())
            ->setCurrency($createPayment->getCurrency())
            ->setCreatedAt(new \DateTime())
            ->setExpiresAt(null)
            ->setTest(true)
            ->setInvoiceUuid(Uuid::fromString($createPayment->getInvoiceUuid()))
            ->setPaymentUuid(Uuid::fromString($paymentUuid))
            ->setSessionId('cs_test_000000000000001')
            ->setRefundable(true)
        ;

        $this->em->persist($payment);

        return $payment;
    }

    public function cancelPayment(Payment $payment): bool
    {
        $fp = fopen('/var/www/stripe/log.txt', 'a+');
        fwrite($fp, "MOCK StripeManager::cancelPayment\n");

        $payment
            ->setStatus(self::STATUS_PAYMENT_CANCELED)
            ->setPaid(false)
        ;

        $this->em->persist($payment);
        $this->em->flush();

        return true;
    }

    /**
     * @throws \Exception
     */
    public function capturePayment(Payment $payment): bool
    {
        $fp = fopen('/var/www/stripe/log.txt', 'a+');
        fwrite($fp, "MOCK StripeManager::capturePayment\n");

        $payment
            ->setStatus(self::STATUS_PAYMENT_SUCCEEDED)
            ->setPaid(true)
            ->setCapturedAt(new \DateTime())
        ;

        $this->em->persist($payment);
        $this->em->flush();

        return true;
    }

    public function updatePayment(Payment $payment)
    {
        $fp = fopen('/var/www/stripe/log.txt', 'a+');
        fwrite($fp, "MOCK StripeManager::updatePayment\n");

        return true;
    }

    public function getPaymentInfo(Payment $payment)
    {
        $fp = fopen('/var/www/stripe/log.txt', 'a+');
        fwrite($fp, "MOCK StripeManager::getPaymentInfo\n");

        $refunds = $this->em->getRepository(Refund::class)->findBy(['payment' => $payment]);

        $refundedAmount = array_reduce($refunds, function (float $carry, Refund $item) {
            return bcadd($carry, $item->getAmount(), 2);
        }, 0);

        return [
            'id' => $payment->getId(),
            'status' => self::STATUS_PAYMENT_REFUND_SUCCEEDED !== $payment->getStatus() ? $payment->getStatus() : self::STATUS_PAYMENT_SUCCEEDED,
            'amount' => $payment->getAmount() * 100,
            'currency' => mb_strtolower($payment->getCurrency()),
            'created' => '1601567899',
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
                        'paid' => $payment->isPaid(),
                        'refunded' => (bool) $refundedAmount,
                    ],
                ],
            ],
        ];
    }

    /**
     * @throws \Exception
     */
    public function refundPayment(Payment $payment, float $amount): Refund
    {
        $fp = fopen('/var/www/stripe/log.txt', 'a+');
        fwrite($fp, "MOCK StripeManager::refundPayment\n");

        $refundResponse = [
            'id' => 're_' . Uuid::uuid4()->toString(),
            'status' => 'succeeded',
            'amount' => $amount * 100,
            'currency' => $payment->getCurrency(),
            'created' => '1601567899',
            'payment_id' => $payment->getPaymentUuid()->toString(),
            'reason' => 'failed_invoice',
        ];

        $refund = $this->createRefundIfNotExists($refundResponse, $payment, false);

        $this->em->persist($payment);
        $this->em->flush();

        return $refund;
    }

    public function createClientByToken(string $token): StripeClient
    {
        $fp = fopen('/var/www/stripe/log.txt', 'a+');
        fwrite($fp, "MOCK StripeManager::createClientByToken\n");

        return new class() extends StripeClient {
            public function me()
            {
                return [
                    'account_id' => 1,
                    'test' => true,
                    'fiscalization_enabled' => true,
                ];
            }
        };
    }
}
