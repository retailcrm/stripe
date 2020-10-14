<?php

namespace App\Entity\PaymentAPIModel;

use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class CreatePaymentFiscalItem
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var float
     * @Serializer\Type("float")
     */
    private $price;

    /**
     * @var float
     * @Serializer\Type("float")
     */
    private $quantity;

    /**
     * @var string
     */
    private $measurementUnit;

    /**
     * @var string
     */
    private $vat;

    /**
     * @var string
     */
    private $paymentMethod;

    /**
     * @var string
     */
    private $paymentObject;

    /**
     * @var string
     */
    private $productCode;

    private $vatAccordance = [
        'none' => 1,
        'vat0' => 2,
        'vat10' => 3,
        'vat110' => 5,
        'vat20' => 4,
        'vat120' => 6,
    ];

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('name', new Assert\NotBlank([
            'groups' => ['api'],
            'message' => 'error.not_blank',
        ]));
        $metadata->addPropertyConstraint('name', new Assert\Length([
            'max' => 255,
            'groups' => ['api'],
            'maxMessage' => 'error.length',
        ]));
        $metadata->addPropertyConstraint('quantity', new Assert\NotBlank([
            'groups' => ['api'],
            'message' => 'error.not_blank',
        ]));
        $metadata->addPropertyConstraint('paymentMethod', new Assert\Choice([
            'choices' => [
                'full_prepayment',
                'prepayment',
                'advance',
                'full_payment',
                'partial_payment',
                'credit',
                'credit_payment',
            ],
            'groups' => ['api'],
            'message' => 'error.incorrect_type',
        ]));
        $metadata->addPropertyConstraint('paymentObject', new Assert\Choice([
            'choices' => [
                'commodity',
                'excise',
                'job',
                'service',
                'gambling_bet',
                'gambling_prize',
                'lottery',
                'lottery_prize',
                'intellectual_activity',
                'payment',
                'agent_commission',
                'property_right',
                'non_operating_gain',
                'insurance_premium',
                'sales_tax',
                'resort_fee',
                'composite',
                'another',
            ],
            'groups' => ['api'],
            'message' => 'error.incorrect_type',
        ]));
        $metadata->addPropertyConstraint('vat', new Assert\NotBlank([
            'groups' => ['apiWithFiscal'],
            'message' => 'error.not_blank',
        ]));
        $metadata->addPropertyConstraint('vat', new Assert\Choice([
            'choices' => [
                'none',
                'vat0',
                'vat10',
                'vat110',
                'vat20',
                'vat120',
            ],
            'groups' => ['apiWithFiscal'],
            'message' => 'error.incorrect_type',
        ]));

        $metadata->addPropertyConstraint('productCode', new Assert\Regex([
            'pattern' => '/^[0-9A-F ]{2,96}$/',
        ]));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return CreatePaymentFiscalItem
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @return CreatePaymentFiscalItem
     */
    public function setPrice(float $price): self
    {
        $this->price = $price;

        return $this;
    }

    /**
     * @return float
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * @return CreatePaymentFiscalItem
     */
    public function setQuantity(float $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * @return string
     */
    public function getMeasurementUnit()
    {
        return $this->measurementUnit;
    }

    /**
     * @param mixed $measurementUnit
     *
     * @return CreatePaymentFiscalItem
     */
    public function setMeasurementUnit(string $measurementUnit): self
    {
        $this->measurementUnit = $measurementUnit;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getVat()
    {
        return $this->vat;
    }

    /**
     * @param mixed $vat
     *
     * @return CreatePaymentFiscalItem
     */
    public function setVat(string $vat): self
    {
        $this->vat = $vat;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPaymentMethod()
    {
        return $this->paymentMethod;
    }

    /**
     * @param mixed $paymentMethod
     *
     * @return CreatePaymentFiscalItem
     */
    public function setPaymentMethod(string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPaymentObject()
    {
        return $this->paymentObject;
    }

    /**
     * @param mixed $paymentObject
     *
     * @return CreatePaymentFiscalItem
     */
    public function setPaymentObject(string $paymentObject): self
    {
        $this->paymentObject = $paymentObject;

        return $this;
    }

    public function getYandexVat()
    {
        return $this->vatAccordance[$this->vat] ?? null;
    }

    /**
     * @return CreatePaymentFiscalItem
     */
    public function setProductCode(?string $productCode): self
    {
        $this->productCode = $productCode;

        return $this;
    }

    public function getProductCode(): ?string
    {
        return $this->productCode;
    }
}
