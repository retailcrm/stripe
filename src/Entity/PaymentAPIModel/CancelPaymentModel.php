<?php

namespace App\Entity\PaymentAPIModel;

use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class CancelPaymentModel extends BasePaymentModel
{
    /**
     * @var PaymentModel
     * @Serializer\Type("PaymentModel")
     */
    private $cancel;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('cancel', new Assert\Valid());
    }

    /**
     * @return CancelPaymentModel
     */
    public function setCancel(PaymentModel $cancel): self
    {
        $this->cancel = $cancel;

        return $this;
    }

    public function getCancel(): PaymentModel
    {
        return $this->cancel;
    }
}
