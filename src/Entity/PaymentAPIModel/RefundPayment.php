<?php

namespace App\Entity\PaymentAPIModel;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class RefundPayment extends PaymentModel
{
    private $amount;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('amount', new Assert\GreaterThan([
            'value' => 0,
            'groups' => ['api'],
        ]));
    }

    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param $amount
     */
    public function setAmount($amount): self
    {
        $this->amount = $amount;

        return $this;
    }
}
