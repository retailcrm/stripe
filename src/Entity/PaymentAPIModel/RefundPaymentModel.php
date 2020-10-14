<?php

namespace App\Entity\PaymentAPIModel;

use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class RefundPaymentModel extends BasePaymentModel
{
    /**
     * @var RefundPayment
     * @Serializer\Type("RefundPayment")
     */
    private $refund;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('refund', new Assert\Valid());
    }

    /**
     * @return RefundPaymentModel
     */
    public function setRefund(RefundPayment $refund): self
    {
        $this->refund = $refund;

        return $this;
    }

    public function getRefund(): RefundPayment
    {
        return $this->refund;
    }
}
