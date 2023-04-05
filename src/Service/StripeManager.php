<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\Payment;
use App\Entity\PaymentAPIModel\CreatePayment;
use App\Entity\Refund;
use App\Factory\StripeClientFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Stripe\StripeClient;
use Symfony\Contracts\Translation\TranslatorInterface;

class StripeManager
{
    public const STATUS_PAYMENT_PENDING = 'requires_payment_method';
    public const STATUS_PAYMENT_PENDING_OLD = 'requires_source';
    public const STATUS_PAYMENT_WAITING_CAPTURE = 'requires_capture';
    public const STATUS_PAYMENT_SUCCEEDED = 'succeeded';
    public const STATUS_PAYMENT_CANCELED = 'canceled';
    public const STATUS_PAYMENT_REFUND_SUCCEEDED = 'refund.succeeded';

    public const STATUSES = [
        self::STATUS_PAYMENT_PENDING => 'Создан',
        self::STATUS_PAYMENT_PENDING_OLD => 'Создан',
        self::STATUS_PAYMENT_WAITING_CAPTURE => 'Оплата ожидает подтверждения',
        self::STATUS_PAYMENT_SUCCEEDED => 'Успешная оплата',
        self::STATUS_PAYMENT_CANCELED => 'Отмена',
        self::STATUS_PAYMENT_REFUND_SUCCEEDED => 'Успешный возврат',
    ];

    public const PAYMENT_TYPE_LINK = 'link';
    public const PAYMENT_TYPES = [self::PAYMENT_TYPE_LINK];

    protected EntityManagerInterface $em;
    private TranslatorInterface $translator;
    private PinbaService $pinbaService;
    private LoggerInterface $logger;
    private CRMConnectManager $connectManager;
    private StripeClientFactory $stripeClientFactory;

    public function __construct(
        EntityManagerInterface $em,
        TranslatorInterface $translator,
        PinbaService $pinbaService,
        LoggerInterface $logger,
        CRMConnectManager $connectManager,
        StripeClientFactory $stripeClientFactory
    ) {
        $this->em = $em;
        $this->translator = $translator;
        $this->pinbaService = $pinbaService;
        $this->logger = $logger;
        $this->connectManager = $connectManager;
        $this->stripeClientFactory = $stripeClientFactory;
    }

    public function getAccountInfo(Account $account)
    {
        $client = $this->createClient($account);

        $stripeAccount = $this->pinbaService->timerHandler(
            [
                'api' => 'stripe',
                'method' => 'accountsRetrieve',
            ],
            static function () use ($client, $account) {
                return $client->accounts->retrieve($account->getAccountId(), []);
            }
        );

        return $stripeAccount;
    }

    public function getCountrySpecs(Account $account)
    {
        $client = $this->createClient($account);
        $stripeAccount = $this->getAccountInfo($account);

        $countrySpec = $this->pinbaService->timerHandler(
            [
                'api' => 'stripe',
                'method' => 'countrySpecsRetrieve',
            ],
            static function () use ($client, $stripeAccount) {
                return $client->countrySpecs->retrieve($stripeAccount['country'], []);
            }
        );

        return $countrySpec;
    }

    /**
     * @throws \Exception
     */
    public function createPayment(Account $account, CreatePayment $createPayment, string $paymentUuid): Payment
    {
        $metadata = [];
        $metadata['invoiceUuid'] = $createPayment->getInvoiceUuid();

        $client = $this->createClient($account);

        $lineItems = [];
        foreach ($createPayment->getItems() as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => $createPayment->getCurrency(),
                    'product_data' => [
                        'name' => mb_substr($item->getName(), 0, 128),
                    ],
                    'unit_amount' => $item->getPrice() * 100,
                ],
                'quantity' => $item->getQuantity(),
            ];
        }

        $returnUrl = $createPayment->getReturnUrl() ?: $createPayment->getSiteUrl();

        $createSession = [
            //'customer' => 'id',
            'payment_method_types' => ['card'],
            'payment_intent_data' => [
                'description' => sprintf(
                    '%s order %s',
                    'simla' == $this->connectManager->getBrand($account->getIntegration()) ? 'Simla' : 'RetailCRM',
                    $createPayment->getOrderNumber()
                ),
                'capture_method' => $account->isApproveManually() ? 'manual' : 'automatic',
                'receipt_email' => $createPayment->getCustomer()->getEmail(),
                'metadata' => $metadata,
            ],
            'customer_email' => $createPayment->getCustomer()->getEmail(),
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $returnUrl,
            'cancel_url' => $returnUrl,
        ];

        $session = $this->pinbaService->timerHandler(
            [
                'api' => 'stripe',
                'method' => 'sessionsСreate',
            ],
            static function () use ($client, $createSession) {
                return $client->checkout->sessions->create($createSession);
            }
        );

        $createdAt = new \DateTime();
        $createdAt->setTimestamp($session['created']);

        $payment = new Payment($account);
        $payment
            ->setId($session['id'])
            ->setStatus(self::STATUS_PAYMENT_PENDING)
            ->setPaid(false)
            ->setAmount($session['amount_total'] / 100)
            ->setCurrency(mb_strtoupper($session['currency']))
            ->setCreatedAt($createdAt)
            ->setExpiresAt(null)
            ->setTest(!$session['livemode'])
            ->setInvoiceUuid(Uuid::fromString($createPayment->getInvoiceUuid()))
            ->setPaymentUuid(Uuid::fromString($paymentUuid))
            ->setSessionId(trim($session['id']))
            ->setRefundable(true)
        ;

        if (isset($paymentIntent['cancellation_reason']) && !empty($paymentIntent['cancellation_reason'])) {
            $payment->setCancellationDetails($this->translator->trans(
                'api.cancellation_reasons.' . $paymentIntent['cancellation_reason']
            ));
        }

        $this->em->persist($payment);

        return $payment;
    }

    /**
     * @throws \Exception
     */
    public function cancelPayment(Payment $payment): bool
    {
        $client = $this->createClient($payment->getAccount());

        $paymentIntent = $this->pinbaService->timerHandler(
            [
                'api' => 'stripe',
                'method' => 'paymentIntentsRetrieve',
            ],
            static function () use ($client, $payment) {
                return $client->paymentIntents->retrieve($payment->getIntentId(), []);
            }
        );

        $this->pinbaService->timerHandler(
            [
                'api' => 'stripe',
                'method' => 'paymentIntentCancel',
            ],
            static function () use ($client, $paymentIntent) {
                $paymentIntent->cancel();
            }
        );

        return true;
    }

    /**
     * @throws \Exception
     */
    public function capturePayment(Payment $payment): bool
    {
        $client = $this->createClient($payment->getAccount());

        $paymentIntent = $this->pinbaService->timerHandler(
            [
                'api' => 'stripe',
                'method' => 'capturePayment',
            ],
            static function () use ($client, $payment) {
                return $client->paymentIntents->capture($payment->getIntentId(), [
                    'amount_to_capture' => $payment->getAmount() * 100,
                ]);
            }
        );

        $charge = $this->getCharge($payment, $paymentIntent['latest_charge']);

        $capturedAt = new \DateTime();
        $capturedAt->setTimestamp($charge['created']);

        $payment
            ->setStatus($paymentIntent['status'])
            ->setPaid($charge['paid'])
            ->setCapturedAt($capturedAt)
        ;

        $this->em->persist($payment);
        $this->em->flush();

        return true;
    }

    /**
     * @throws \Exception
     */
    public function getPaymentInfo(Payment $payment)
    {
        $client = $this->createClient($payment->getAccount());

        return $this->pinbaService->timerHandler(
            [
                'api' => 'stripe',
                'method' => 'paymentIntentsRetrieve',
            ],
            static function () use ($client, $payment) {
                return $client->paymentIntents->retrieve($payment->getIntentId(), []);
            }
        );
    }

    /**
     * @throws \Exception
     */
    public function getCharge(Payment $payment, string $id, bool $expandRefunds = false)
    {
        $client = $this->createClient($payment->getAccount());

        $params = $expandRefunds ? ['expand' => ['refunds']] : [];

        return $this->pinbaService->timerHandler(
            [
                'api' => 'stripe',
                'method' => 'chargeRetrieve',
            ],
            static function () use ($client, $id, $params) {
                return $client->charges->retrieve($id, $params);
            }
        );
    }

    /**
     * @throws \Exception
     */
    public function refundPayment(Payment $payment, float $amount): Refund
    {
        $client = $this->createClient($payment->getAccount());

        $refundRequest = [
            'payment_intent' => $payment->getIntentId(),
            'amount' => $amount * 100,
        ];

        $refundResponse = $this->pinbaService->timerHandler(
            [
                'api' => 'stripe',
                'method' => 'refundsCreate',
            ],
            static function () use ($client, $refundRequest) {
                return $client->refunds->create($refundRequest);
            }
        );

        $refund = $this->createRefundIfNotExists($refundResponse, $payment);
        $this->em->flush();

        return $refund;
    }

    public function createRefundIfNotExists($refundResponse, Payment $payment, $fromNotification = false): Refund
    {
        $refund = $this->em->getRepository(Refund::class)->find($refundResponse['id']);
        if ($refund instanceof Refund) {
            return $refund;
        }

        $createdAt = new \DateTime();
        $createdAt->setTimestamp($refundResponse['created']);

        $amount = $refundResponse['amount'] / 100;

        $comment = null;
        if (isset($refundResponse['reason']) && !empty($refundResponse['reason'])) {
            $comment = $this->translator->trans(
                'api.cancellation_reasons.' . $refundResponse['reason']
            );
        }

        $refund = new Refund();
        $refund
            ->setId($refundResponse['id'])
            ->setStatus($refundResponse['status'])
            ->setAmount($amount)
            ->setCurrency(mb_strtoupper($refundResponse['currency']))
            ->setCreatedAt($createdAt)
            ->setPayment($payment)
            ->setComment($comment)
            ->setFromNotification($fromNotification)
        ;

        $this->em->persist($refund);

        $payment->setRefundedAmount(bcadd($payment->getRefundedAmount(), $amount, 2));

        /* If not last refund */
        if (1 !== bccomp($payment->getAmount(), $payment->getRefundedAmount(), 2)) {
            $payment->setStatus(self::STATUS_PAYMENT_REFUND_SUCCEEDED);
        }

        return $refund;
    }

    /**
     * @return bool
     *
     * @throws \Exception
     */
    public function updatePayment(Payment $payment)
    {
        $client = $this->createClient($payment->getAccount());

        $paymentIntent = $this->pinbaService->timerHandler(
            [
                'api' => 'stripe',
                'method' => 'paymentIntentsRetrieve',
            ],
            static function () use ($client, $payment) {
                return $client->paymentIntents->retrieve($payment->getIntentId(), []);
            }
        );

        $charge = $this->getCharge($payment, $paymentIntent['latest_charge']);
        $cancellationDetailsReason = $payment->getCancellationDetails();

        if (isset($paymentIntent['cancellation_reason']) && !empty($paymentIntent['cancellation_reason'])) {
            $cancellationDetailsReason = $this->translator->trans(
                'api.cancellation_reasons.' . $paymentIntent['cancellation_reason']
            );
        }

        $capturedAt = new \DateTime();
        $capturedAt->setTimestamp($charge['created']);

        $expiresAt = null;
        if (self::STATUS_PAYMENT_WAITING_CAPTURE === $paymentIntent['status']
            && null === $payment->getExpiresAt()
        ) {
            $expiresAt = new \DateTime('+7 DAYS');
        }

        $payment
            ->setStatus($paymentIntent['status'])
            ->setExpiresAt($expiresAt)
            ->setPaid($charge['paid'])
            ->setCapturedAt($capturedAt)
            ->setAmount($paymentIntent['amount'] / 100)
            ->setCancellationDetails($cancellationDetailsReason)
        ;

        $this->em->persist($payment);
        $this->em->flush();

        return true;
    }

    public function createClient(Account $account): StripeClient
    {
        return $this->stripeClientFactory->create($account, $this->logger);
    }
}
