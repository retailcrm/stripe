<?php

namespace App\Entity\PaymentAPIModel;

use App\Entity\Account;
use App\Service\StripeManager;
use App\Validator\Constraints\EntityExists;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class CreatePayment
{
    /**
     * @var string
     */
    private $shopId;

    /**
     * @var string
     */
    private $invoiceType;

    /**
     * @var float
     * @Serializer\Type("float")
     */
    private $amount;

    /**
     * @var string
     */
    private $currency;

    /**
     * @var string
     */
    private $orderNumber;
    /**
     * @var CreatePaymentFiscalItem[]
     * @Serializer\Type("ArrayCollection<CreatePaymentFiscalItem>")
     */
    private $items;
    /**
     * @var CreatePaymentCustomer
     * @Serializer\Type("CreatePaymentCustomer")
     */
    private $customer;

    /**
     * @var string
     */
    private $invoiceUuid;

    /**
     * @var string
     */
    private $siteUrl;

    /**
     * @var string
     */
    private $returnUrl;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('shopId', new Assert\Length([
            'max' => 255,
            'groups' => ['api'],
            'maxMessage' => 'error.length',
        ]));
        $metadata->addPropertyConstraint('shopId', new Assert\NotBlank([
            'groups' => ['api'],
            'message' => 'error.not_blank',
        ]));
        $metadata->addPropertyConstraint('shopId', new Assert\Uuid([
            'groups' => ['api'],
            'message' => 'error.not_valid_uuid',
        ]));
        $metadata->addPropertyConstraint('shopId', new EntityExists([
            'groups' => ['api'],
            'message' => 'error.entity_not_exists',
            'entity' => Account::class,
            'field' => 'id',
            'method' => 'find',
        ]));
        $metadata->addPropertyConstraint('invoiceType', new Assert\Choice([
            'choices' => StripeManager::PAYMENT_TYPES,
            'groups' => ['api'],
            'message' => 'error.incorrect_type',
        ]));
        $metadata->addPropertyConstraint('orderNumber', new Assert\NotBlank([
            'groups' => ['api'],
            'message' => 'error.not_blank',
        ]));
        $metadata->addPropertyConstraint('amount', new Assert\NotBlank([
            'groups' => ['api'],
            'message' => 'error.not_blank',
        ]));
        $metadata->addPropertyConstraint('currency', new Assert\NotBlank([
            'groups' => ['api'],
            'message' => 'error.not_blank',
        ]));
        $metadata->addPropertyConstraint('currency', new Assert\Currency([
            'groups' => ['api'],
            'message' => 'error.incorrect_currency',
        ]));
        $metadata->addPropertyConstraint('customer', new Assert\Valid());
        $metadata->addPropertyConstraint('customer', new Assert\NotBlank([
            'groups' => ['apiWithFiscal'],
            'message' => 'error.not_blank',
        ]));
        $metadata->addPropertyConstraint('items', new Assert\Valid());
        $metadata->addPropertyConstraint('invoiceUuid', new Assert\NotBlank([
            'groups' => ['api'],
            'message' => 'error.not_blank',
        ]));
        $metadata->addPropertyConstraint('invoiceUuid', new Assert\Uuid([
            'groups' => ['api'],
            'message' => 'error.not_valid_uuid',
        ]));
        $metadata->addPropertyConstraint('siteUrl', new Assert\NotBlank([
            'groups' => ['api'],
            'message' => 'error.not_blank',
        ]));
        $metadata->addPropertyConstraint('siteUrl', new Assert\Url([
            'groups' => ['api'],
            'message' => 'error.url',
        ]));
        $metadata->addPropertyConstraint('returnUrl', new Assert\Url([
            'groups' => ['api'],
            'message' => 'error.url',
        ]));
    }

    /**
     * @return mixed
     */
    public function getShopId()
    {
        return $this->shopId;
    }

    /**
     * @param mixed $shopId
     *
     * @return CreatePayment
     */
    public function setShopId($shopId): self
    {
        $this->shopId = $shopId;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getInvoiceType()
    {
        return $this->invoiceType;
    }

    /**
     * @param mixed $invoiceType
     *
     * @return CreatePayment
     */
    public function setInvoiceType($invoiceType): self
    {
        $this->invoiceType = $invoiceType;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param mixed $amount
     *
     * @return CreatePayment
     */
    public function setAmount($amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param mixed $currency
     *
     * @return CreatePayment
     */
    public function setCurrency($currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getOrderNumber()
    {
        return $this->orderNumber;
    }

    /**
     * @param mixed $orderNumber
     *
     * @return CreatePayment
     */
    public function setOrderNumber($orderNumber): self
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    /**
     * @return CreatePaymentFiscalItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param CreatePaymentFiscalItem[] $items
     *
     * @return CreatePayment
     */
    public function setItems(array $items): self
    {
        $this->items = $items;

        return $this;
    }

    public function getCustomer(): CreatePaymentCustomer
    {
        return $this->customer;
    }

    /**
     * @return CreatePayment
     */
    public function setCustomer(CreatePaymentCustomer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getInvoiceUuid(): string
    {
        return $this->invoiceUuid;
    }

    public function setInvoiceUuid(string $invoiceUuid): self
    {
        $this->invoiceUuid = $invoiceUuid;

        return $this;
    }

    public function getSiteUrl(): string
    {
        return $this->siteUrl;
    }

    /**
     * @return CreatePayment
     */
    public function setSiteUrl(string $siteUrl): self
    {
        $this->siteUrl = $siteUrl;

        return $this;
    }

    public function getReturnUrl(): ?string
    {
        return $this->returnUrl;
    }

    public function setReturnUrl(?string $returnUrl): self
    {
        $this->returnUrl = $returnUrl;

        return $this;
    }
}
