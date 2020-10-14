<?php

namespace App\Entity\PaymentAPIModel;

use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class CreatePaymentModel extends BasePaymentModel
{
    /**
     * @var CreatePayment
     * @Serializer\Type("CreatePayment")
     */
    private $create;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('create', new Assert\Valid());
    }

    public function getCreate(): CreatePayment
    {
        return $this->create;
    }

    /**
     * @return CreatePaymentModel
     */
    public function setCreate(CreatePayment $create): self
    {
        $this->create = $create;

        return $this;
    }
}
