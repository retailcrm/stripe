<?php

namespace App\Tests;

use App\Entity\PaymentAPIModel\CreatePaymentCustomer;
use App\Service\StripeManager;
use Ramsey\Uuid\Uuid;

final class RequestPaymentAPIHelper
{
    public static function createPaymentParams($integrationId, $accountId): array
    {
        return [
            'clientId' => $integrationId,
            'create' => json_encode([
                'invoiceUuid' => Uuid::uuid4()->toString(),
                'shopId' => $accountId,
                'invoiceType' => StripeManager::PAYMENT_TYPE_LINK,
                'amount' => 121,
                'currency' => 'RUB',
                'orderNumber' => '1213',
                'siteUrl' => 'http://redirect.local',
                'returnUrl' => 'http://return.url',
                'customer' => [
                    'email' => 'sad@madss.rj',
                    'phone' => '79004488445',
                    'firstName' => 'Mark',
                    'lastName' => 'Tlen',
                    'patronymic' => 'Иванович',
                    'sex' => 'male',
                    'contragentType' => CreatePaymentCustomer::TYPE_LEGAL_ENTITY,
                    'legalName' => 'ООО Фрукты и овощи',
                    'INN' => '362084448163',
                ],
                'items' => [
                    [
                        'name' => 'Колбаса',
                        'price' => 1234,
                        'quantity' => 2,
                        'measurementUnit' => 'кг',
                        'vat' => 'vat10',
                        'paymentMethod' => 'advance',
                        'paymentObject' => 'payment',
                    ],
                    [
                        'name' => 'Сигареты',
                        'price' => 123,
                        'quantity' => 12,
                        'measurementUnit' => 'шт',
                        'vat' => 'vat10',
                        'paymentMethod' => 'advance',
                        'paymentObject' => 'payment',
                        'productCode' => '44 4D 04 2F F7 5C 76 70 4E 34 4E 35 37 52 53 43 42 55 5A 54 51',
                    ],
                ],
            ]),
        ];
    }

    // Не передан параметр create
    public static function createEmptyPaymentParams($integrationId): array
    {
        return [
            'clientId' => $integrationId,
        ];
    }

    public static function createCancelParams($integrationId, $paymentId)
    {
        return self::createBaseApiParams($integrationId, $paymentId, 'cancel');
    }

    public static function createBadCancelParamsArray($integrationId, $paymentId): array
    {
        return [
            self::createBaseApiParams($integrationId, 0, 'cancel'),
            self::createBaseApiParams(0, $paymentId, 'cancel'),
            self::notExistsOperation($integrationId),
            self::notExistsClientId($paymentId, 'cancel'),
        ];
    }

    public static function createApproveParams($integrationId, $paymentId)
    {
        return self::createBaseApiParams($integrationId, $paymentId, 'approve');
    }

    public static function createBadApproveParamsArray($integrationId, $paymentId): array
    {
        return [
            self::createBaseApiParams($integrationId, 0, 'approve'),
            self::createBaseApiParams(0, $paymentId, 'approve'),
            self::notExistsOperation($integrationId),
            self::notExistsClientId($paymentId, 'approve'),
        ];
    }

    public static function createStatusParams($integrationId, $paymentId)
    {
        return [
            'clientId' => $integrationId,
            'paymentId' => $paymentId,
        ];
    }

    public static function createBadStatusParamsArray($integrationId, $paymentId): array
    {
        return [
            [
                'clientId' => 0,
                'paymentId' => $paymentId,
            ],
            [
                'clientId' => $integrationId,
                'paymentId' => 0,
            ],
        ];
    }

    public static function createRefundParams($integrationId, $paymentId)
    {
        return self::createBaseApiParams($integrationId, $paymentId, 'refund');
    }

    public static function createRefundParamsWithAmount($integrationId, $paymentId, $amount): array
    {
        return [
            'clientId' => $integrationId,
            'refund' => json_encode([
                'paymentId' => $paymentId,
                'amount' => $amount,
            ]),
        ];
    }

    public static function createBadRefundParamsArray($integrationId, $paymentId): array
    {
        return [
            self::createBaseApiParams($integrationId, 0, 'refund'),
            self::createBaseApiParams(0, $paymentId, 'refund'),
            self::notExistsOperation($integrationId),
            self::notExistsClientId($paymentId, 'refund'),
        ];
    }

    private static function createBaseApiParams($integrationId, $paymentId, $operation): array
    {
        return [
            'clientId' => $integrationId,
            $operation => json_encode([
                'paymentId' => $paymentId,
            ]),
        ];
    }

    private static function notExistsOperation($integrationId): array
    {
        return [
            'clientId' => $integrationId,
        ];
    }

    private static function notExistsClientId($paymentId, $operation): array
    {
        return [
            $operation => json_encode([
                'paymentId' => $paymentId,
            ]),
        ];
    }
}
