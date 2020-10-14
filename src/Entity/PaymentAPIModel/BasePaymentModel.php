<?php

namespace App\Entity\PaymentAPIModel;

use App\Entity\Integration;
use App\Validator\Constraints\EntityExists;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class BasePaymentModel
{
    /**
     * @var string
     */
    private $clientId;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('clientId', new Assert\Length([
            'max' => 255,
            'groups' => ['api'],
            'maxMessage' => 'error.length',
        ]));
        $metadata->addPropertyConstraint('clientId', new Assert\NotBlank([
            'groups' => ['api'],
            'message' => 'error.not_blank',
        ]));
        $metadata->addPropertyConstraint('clientId', new Assert\Uuid([
            'groups' => ['api'],
            'message' => 'error.not_valid_uuid',
        ]));
        $metadata->addPropertyConstraint('clientId', new EntityExists([
            'groups' => ['api'],
            'message' => 'error.entity_not_exists',
            'entity' => Integration::class,
            'field' => 'id',
            'method' => 'find',
        ]));
    }

    /**
     * @return mixed
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * @param mixed $clientId
     *
     * @return BasePaymentModel
     */
    public function setClientId($clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }
}
