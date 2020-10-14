<?php

namespace App\Entity\PaymentAPIModel;

use App\Entity\Payment as EntityPayment;
use App\Validator\Constraints\EntityExists;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class StatusModel extends BasePaymentModel
{
    /**
     * @var string
     */
    private $paymentId;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('paymentId', new Assert\NotBlank([
            'groups' => ['api'],
            'message' => 'error.not_blank',
        ]));
        $metadata->addPropertyConstraint('paymentId', new EntityExists([
            'groups' => ['api'],
            'message' => 'error.entity_not_exists',
            'entity' => EntityPayment::class,
            'field' => 'paymentUuid',
        ]));
    }

    /**
     * @return string
     */
    public function getPaymentId()
    {
        return $this->paymentId;
    }

    /**
     * @param string $paymentId
     *
     * @return StatusModel
     */
    public function setPaymentId($paymentId): self
    {
        $this->paymentId = $paymentId;

        return $this;
    }
}
