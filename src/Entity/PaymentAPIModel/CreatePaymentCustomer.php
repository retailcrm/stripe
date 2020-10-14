<?php

namespace App\Entity\PaymentAPIModel;

use App\Validator\Constraints\Inn;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class CreatePaymentCustomer
{
    public const TYPE_INDIVIDUAL = 'individual';
    public const TYPE_LEGAL_ENTITY = 'legal-entity';
    public const TYPE_ENTERPRENEUR = 'enterpreneur';

    /**
     * Типы, для которых необходимо указывать ИНН и наименование.
     */
    public const LEGAL_TYPES = [self::TYPE_LEGAL_ENTITY, self::TYPE_ENTERPRENEUR];

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $phone;

    /**
     * @var string
     */
    private $firstName;

    /**
     * @var string
     */
    private $lastName;

    /**
     * @var string
     */
    private $patronymic;

    /**
     * @var string
     */
    private $sex;

    /**
     * @var string
     */
    protected $contragentType;

    /**
     * @var string
     */
    protected $legalName;

    /**
     * @var string
     */
    protected $INN;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('email', new Assert\Length([
            'max' => 255,
            'groups' => ['api'],
            'maxMessage' => 'error.length',
        ]));
        $metadata->addPropertyConstraint('phone', new Assert\Length([
            'max' => 255,
            'groups' => ['api'],
            'maxMessage' => 'error.length',
        ]));
        $metadata->addPropertyConstraint('firstName', new Assert\Length([
            'max' => 255,
            'groups' => ['api'],
            'maxMessage' => 'error.length',
        ]));
        $metadata->addPropertyConstraint('lastName', new Assert\Length([
            'max' => 255,
            'groups' => ['api'],
            'maxMessage' => 'error.length',
        ]));
        $metadata->addPropertyConstraint('patronymic', new Assert\Length([
            'max' => 255,
            'groups' => ['api'],
            'maxMessage' => 'error.length',
        ]));
        $metadata->addPropertyConstraint('legalName', new Assert\Length([
            'max' => 255,
            'groups' => ['api'],
            'maxMessage' => 'error.length',
        ]));
        $metadata->addPropertyConstraint('sex', new Assert\Choice([
            'choices' => ['male', 'female'],
            'groups' => ['api'],
            'message' => 'error.incorrect_type',
        ]));
        $metadata->addPropertyConstraint('contragentType', new Assert\Choice([
            'choices' => ['individual', 'legal-entity', 'enterpreneur'],
            'groups' => ['api'],
            'message' => 'error.incorrect_type',
        ]));
        $metadata->addConstraint(new Assert\Expression([
            'expression' => 'this.getEmail() or this.getPhone()',
            'groups' => ['apiWithFiscal'],
            'message' => 'error.need_email_or_phone',
        ]));
        $metadata->addConstraint(new Assert\Expression([
            'expression' => 'this.getContragentType() == "individual" or (this.getINN() and this.getLegalName())',
            'groups' => ['apiWithFiscal'],
            'message' => 'error.need_inn_and_legal_name',
        ]));
        $metadata->addPropertyConstraint('INN', new Inn([
            'groups' => ['apiWithFiscal'],
            'message' => 'error.wrong_inn',
        ]));
        $metadata->addPropertyConstraint('contragentType', new Assert\NotBlank([
            'groups' => ['apiWithFiscal'],
            'message' => 'error.not_blank',
        ]));
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     *
     * @return CreatePaymentCustomer
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @param mixed $phone
     *
     * @return CreatePaymentCustomer
     */
    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * @param mixed $firstName
     *
     * @return CreatePaymentCustomer
     */
    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * @param mixed $lastName
     *
     * @return CreatePaymentCustomer
     */
    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPatronymic()
    {
        return $this->patronymic;
    }

    /**
     * @param mixed $patronymic
     *
     * @return CreatePaymentCustomer
     */
    public function setPatronymic(string $patronymic): self
    {
        $this->patronymic = $patronymic;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSex()
    {
        return $this->sex;
    }

    /**
     * @param mixed $sex
     *
     * @return CreatePaymentCustomer
     */
    public function setSex(string $sex): self
    {
        $this->sex = $sex;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getContragentType()
    {
        return $this->contragentType;
    }

    /**
     * @return CreatePaymentCustomer
     */
    public function setContragentType(string $contragentType): self
    {
        $this->contragentType = $contragentType;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLegalName()
    {
        return $this->legalName;
    }

    /**
     * @return CreatePaymentCustomer
     */
    public function setLegalName(string $legalName): self
    {
        $this->legalName = $legalName;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getINN()
    {
        return $this->INN;
    }

    /**
     * @return CreatePaymentCustomer
     */
    public function setINN(string $INN): self
    {
        $this->INN = $INN;

        return $this;
    }
}
