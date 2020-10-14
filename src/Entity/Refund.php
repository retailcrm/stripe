<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(
 *     name="i_refund"
 * )
 */
class Refund
{
    /**
     * @var string
     */
    public const STATUS_SUCCEEDED = 'succeeded';

    /**
     * @var string
     */
    public const STATUS_CANCELED = 'canceled';

    /**
     * @var string Уникальный идентфикатор
     *
     * @ORM\Id
     * @ORM\Column(type="string")
     */
    protected $id;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $status;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $amount;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $currency;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=true)
     */
    protected $createdAt;

    /**
     * @var Payment
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Payment", inversedBy="refunds")
     * @ORM\JoinColumn(name="payment_id", referencedColumnName="id", nullable=false)
     */
    protected $payment;

    /**
     * @var string|null
     *
     * @ORM\Column(name="comment", length=255, nullable=true)
     */
    protected $comment;

    /**
     * @var bool
     *
     * @ORM\Column(name="from_notification", type="boolean", options={"default"=false})
     */
    protected $fromNotification;

    public function __construct()
    {
        $this->fromNotification = false;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function setPayment(Payment $payment): self
    {
        $this->payment = $payment;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function isFromNotification(): bool
    {
        return $this->fromNotification;
    }

    public function setFromNotification(bool $fromNotification): self
    {
        $this->fromNotification = $fromNotification;

        return $this;
    }
}
