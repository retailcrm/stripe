<?php

namespace App\Entity\Model;

use App\Entity\Refund as EntityRefund;
use App\Validator\Constraints\EntityExists;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class RefundRequest
{
    /**
     * @var string
     * @Serializer\Type("string")
     */
    protected $id;

    /**
     * @var string
     * @Serializer\Type("string")
     */
    protected $status;

    /**
     * @var float
     * @Serializer\Type("float")
     */
    protected $amount;

    /**
     * @var string|null
     * @Serializer\Type("string")
     */
    protected $comment;

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata
            ->addPropertyConstraints('id', [
                new Assert\NotBlank(['message' => 'error.not_blank']),
                new EntityExists([
                    'message' => 'error.entity_not_exists',
                    'entity' => EntityRefund::class,
                    'field' => 'id',
                ]),
            ])
            ->addPropertyConstraints('status', [
                new Assert\Length(['max' => 50, 'maxMessage' => 'error.length']),
                new Assert\Choice([
                    'choices' => [
                        EntityRefund::STATUS_SUCCEEDED,
                        EntityRefund::STATUS_CANCELED,
                    ],
                    'message' => 'error.incorrect_type',
                ]),
            ])
            ->addPropertyConstraints('amount', [
                new Assert\GreaterThan(['value' => 0, 'message' => 'error.greater_than']),
            ]);
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return RefundRequest
     */
    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return RefundRequest
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @return RefundRequest
     */
    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @return RefundRequest
     */
    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }
}
