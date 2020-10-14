<?php

namespace App\Entity\PaymentAPIModel;

use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class ApprovePaymentModel extends BasePaymentModel
{
    /**
     * @var PaymentModel
     * @Serializer\Type("PaymentModel")
     */
    private $approve;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('approve', new Assert\Valid());
    }

    /**
     * @return ApprovePaymentModel
     */
    public function setApprove(PaymentModel $approve): self
    {
        $this->approve = $approve;

        return $this;
    }

    public function getApprove(): PaymentModel
    {
        return $this->approve;
    }
}
