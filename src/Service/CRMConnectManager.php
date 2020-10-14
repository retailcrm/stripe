<?php

namespace App\Service;

use App\Entity\Integration;
use App\Entity\Model\IntegrationModule;
use App\Entity\Model\IntegrationPayment;
use App\Entity\Model\RefundRequest;
use App\Entity\Model\UpdateInvoiceRequest;
use App\Entity\Payment;
use App\Entity\Refund;
use App\Exception\CreateUpdateInvoiceException;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use RetailCrm\ApiClient;
use RetailCrm\Exception\CurlException;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CRMConnectManager
{
    public const MODULE_NAME = 'Stripe';
    public const MODULE_LOGO = '/build/images/stripe_logo.svg';
    public const MODULE_CODE = 'stripe';

    public const PAYMENT_CREATE_METHOD = 'create';
    public const PAYMENT_APPROVE_METHOD = 'approve';
    public const PAYMENT_CANCEL_METHOD = 'cancel';
    public const PAYMENT_STATUS_METHOD = 'status';
    public const PAYMENT_REFUND_METHOD = 'refund';

    public const CRM_INVOICE_STATUS_MAP = [
        StripeManager::STATUS_PAYMENT_PENDING => 'pending',
        StripeManager::STATUS_PAYMENT_WAITING_CAPTURE => 'waitingForCapture',
        StripeManager::STATUS_PAYMENT_SUCCEEDED => 'succeeded',
        StripeManager::STATUS_PAYMENT_CANCELED => 'canceled',
        StripeManager::STATUS_PAYMENT_REFUND_SUCCEEDED => 'refundSucceeded',
    ];

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var Packages
     */
    private $packages;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var PinbaService
     */
    private $pinbaService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * CRMConnectManager constructor.
     */
    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        Packages $packages,
        SerializerInterface $serializer,
        ParameterBagInterface $parameterBag,
        ValidatorInterface $validator,
        PinbaService $pinbaService,
        LoggerInterface $logger
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->packages = $packages;
        $this->serializer = $serializer;
        $this->parameterBag = $parameterBag;
        $this->validator = $validator;
        $this->pinbaService = $pinbaService;
        $this->logger = $logger;
    }

    public function sendModuleInCRM(Integration $integration): bool
    {
        $module = $this->createModule($integration);

        return $this->sendCrmRequest($module, $integration);
    }

    public function checkInvoice(Payment $payment): bool
    {
        $integration = $payment->getAccount()->getIntegration();
        $client = $this->createApiClient($integration);

        $response = $this->pinbaService->timerHandler(
            [
                'api' => 'retailCrm',
                'method' => 'paymentCheckInvoice',
            ],
            static function () use ($client, $payment) {
                return $client->request->paymentCheckInvoice([
                    'invoiceUuid' => $payment->getInvoiceUuid(),
                    'amount' => $payment->getAmount(),
                    'currency' => $payment->getCurrency(),
                ]);
            }
        );

        return $response->isSuccessful() && $response['success'];
    }

    /**
     * @throws CreateUpdateInvoiceException
     */
    public function updateInvoice(Payment $payment, bool $withStatus = true, ?Refund $refund = null): bool
    {
        $integration = $payment->getAccount()->getIntegration();
        $client = $this->createApiClient($integration);
        $params = $this->createUpdateInvoiceRequest($payment, $withStatus, $refund);

        $response = $this->pinbaService->timerHandler(
            [
                'api' => 'retailCrm',
                'method' => 'paymentUpdateInvoice',
            ],
            static function () use ($client, $params) {
                return $client->request->paymentUpdateInvoice($params);
            }
        );

        return $response->isSuccessful() && $response['success'];
    }

    /**
     * @throws CreateUpdateInvoiceException
     */
    private function createUpdateInvoiceRequest(Payment $payment, bool $withStatus = true, ?Refund $refund = null): array
    {
        $invoiceUrl = $this->urlGenerator->generate(
            'short_url',
            ['slug' => $payment->getUrl()->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $updateInvoiceRequest = new UpdateInvoiceRequest();
        $updateInvoiceRequest
            ->setAmount($payment->getAmount())
            ->setInvoiceUuid($payment->getInvoiceUuid())
            ->setPaymentId($payment->getPaymentUuid())
            ->setCancellationDetails($payment->getCancellationDetails())
            ->setInvoiceUrl($invoiceUrl)
            ->setPaidAt($payment->getCapturedAt())
            ->setExpiredAt($payment->getExpiresAt())
            ->setRefundable($payment->isRefundable())
            ->setCancellable(true);

        if ($refund) {
            $refundRequest = new RefundRequest();
            $refundRequest
                ->setStatus($refund->getStatus())
                ->setId($refund->getId())
                ->setComment($refund->getComment())
                ->setAmount($refund->getAmount());

            $updateInvoiceRequest->setRefund($refundRequest);
        }

        if ($withStatus) {
            $invoiceStatus = $payment->getStatus();
            if (array_key_exists($payment->getStatus(), self::CRM_INVOICE_STATUS_MAP)) {
                $invoiceStatus = self::CRM_INVOICE_STATUS_MAP[$payment->getStatus()];
            }

            $updateInvoiceRequest
                ->setStatus($invoiceStatus);
        }

        $errors = $this->validator->validate($updateInvoiceRequest);
        if (count($errors) > 0) {
            throw new CreateUpdateInvoiceException((string) $errors, 500);
        }

        return $this->serializer->toArray($updateInvoiceRequest);
    }

    private function createModule(Integration $integration): IntegrationModule
    {
        $accountUrl = $this->urlGenerator->generate(
            'stripe_settings',
            ['slug' => $integration->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $domain = 'https://' . $this->parameterBag->get('domain');

        $payment = new IntegrationPayment();

        $payment
            ->setActions($this->getPaymentActions())
            ->setCurrencies([
                'USD', 'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BDT', 'BGN',
                'BIF', 'BMD', 'BND', 'BOB', 'BRL', 'BSD', 'BWP', 'BZD', 'CAD', 'CDF', 'CHF', 'CLP', 'CNY', 'COP', 'CRC',
                'CVE', 'CZK', 'DJF', 'DKK', 'DOP', 'DZD', 'EGP', 'ETB', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL', 'GIP', 'GMD',
                'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS', 'INR', 'ISK', 'JMD', 'JPY', 'KES',
                'KGS', 'KHR', 'KMF', 'KRW', 'KYD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'MAD', 'MDL', 'MGA', 'MKD',
                'MMK', 'MNT', 'MOP', 'MRO', 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR',
                'NZD', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RSD', 'RUB', 'RWF', 'SAR', 'SBD',
                'SCR', 'SEK', 'SGD', 'SHP', 'SLL', 'SOS', 'SRD', 'STD', 'SZL', 'THB', 'TJS', 'TOP', 'TRY', 'TTD', 'TWD',
                'TZS', 'UAH', 'UGX', 'UYU', 'UZS', 'VND', 'VUV', 'WST', 'XAF', 'XCD', 'XOF', 'XPF', 'YER', 'ZAR', 'ZMW',
            ])
            ->setShops($integration->getAccountsForCRM())
            ->setInvoiceTypes(['link']);

        $module = new IntegrationModule();

        $module
            ->setAccountUrl($accountUrl)
            ->setActions(['activity' => $this->urlGenerator->generate('crm_connection_activity')])
            ->setBaseUrl($domain)
            ->setClientId($integration->getSlug())
            ->setIntegrations(['payment' => $payment])
            ->setName(self::MODULE_NAME)
            ->setLogo($domain . $this->packages->getUrl(self::MODULE_LOGO))
            ->setActive($integration->isActive())
        ;

        return $module;
    }

    private function sendCrmRequest(IntegrationModule $module, Integration $integration): bool
    {
        $data = $this->serializer->toArray($module);

        $client = $this->createApiClient($integration);
        try {
            $response = $this->pinbaService->timerHandler(
                [
                    'api' => 'retailCrm',
                    'method' => 'integrationModulesEdit',
                ],
                static function () use ($client, $data) {
                    return $client->request->integrationModulesEdit($data);
                }
            );
        } catch (CurlException $e) {
            return false;
        }

        return $response->isSuccessful() && $response['success'];
    }

    private function getPaymentActions(): array
    {
        return [
            self::PAYMENT_CREATE_METHOD => $this->urlGenerator->generate('crm_payment_create', [], UrlGeneratorInterface::ABSOLUTE_PATH),
            self::PAYMENT_APPROVE_METHOD => $this->urlGenerator->generate('crm_payment_approve', [], UrlGeneratorInterface::ABSOLUTE_PATH),
            self::PAYMENT_CANCEL_METHOD => $this->urlGenerator->generate('crm_payment_cancel', [], UrlGeneratorInterface::ABSOLUTE_PATH),
            self::PAYMENT_STATUS_METHOD => $this->urlGenerator->generate('crm_payment_status', [], UrlGeneratorInterface::ABSOLUTE_PATH),
            self::PAYMENT_REFUND_METHOD => $this->urlGenerator->generate('crm_payment_refund', [], UrlGeneratorInterface::ABSOLUTE_PATH),
        ];
    }

    private function createApiClient(Integration $integration, string $version = ApiClient::V5): ApiClient
    {
        $client = new ApiClient($integration->getCrmUrl(), $integration->getCrmApiKey(), $version);
        $client->setLogger($this->logger);

        return $client;
    }
}
