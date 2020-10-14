<?php

namespace App\Entity\Model;

use App\Entity\Payment as EntityPayment;
use App\Validator\Constraints\EntityExists;
use JMS\Serializer\Annotation as Serializer;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class UpdateInvoiceRequest
{
    /**
     * @var string
     * @Serializer\Type("string")
     */
    protected $invoiceUuid;
    /**
     * @var string
     * @Serializer\Type("string")
     */
    protected $paymentId;
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
     * @var string
     * @Serializer\Type("string")
     */
    protected $cancellationDetails;
    /**
     * @var string
     * @Serializer\Type("string")
     */
    protected $invoiceUrl;
    /**
     * @var string
     * @Serializer\Type("DateTime<'Y-m-d H:i:s'>")
     */
    protected $paidAt;
    /**
     * @var string
     * @Serializer\Type("DateTime<'Y-m-d H:i:s'>")
     */
    protected $expiredAt;
    /**
     * @var bool
     * @Serializer\Type("boolean")
     */
    protected $refundable;
    /**
     * @var RefundRequest
     * @Serializer\SerializedName("refund")
     * @Serializer\Type("App\Entity\Model\RefundRequest")
     */
    protected $refund;
    /**
     * @var bool
     * @Serializer\Type("boolean")
     */
    protected $cancellable;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('invoiceUuid', new Assert\NotBlank([
            'message' => 'error.not_blank',
        ]));
        $metadata->addPropertyConstraint('invoiceUuid', new Assert\Uuid([
            'message' => 'error.not_valid_uuid',
        ]));
        $metadata->addPropertyConstraint('paymentId', new Assert\Uuid([
            'message' => 'error.not_valid_uuid',
        ]));
        $metadata->addPropertyConstraint('paymentId', new EntityExists([
            'message' => 'error.entity_not_exists',
            'entity' => EntityPayment::class,
            'field' => 'paymentUuid',
        ]));
        $metadata->addPropertyConstraint('paymentId', new Assert\Length([
            'max' => 255,
            'maxMessage' => 'error.length',
        ]));
        $metadata->addPropertyConstraint('status', new Assert\Length([
            'max' => 50,
            'maxMessage' => 'error.length',
        ]));
        $metadata->addPropertyConstraint('cancellationDetails', new Assert\Length([
            'max' => 255,
            'maxMessage' => 'error.length',
        ]));
        $metadata->addPropertyConstraint('invoiceUrl', new Assert\Url([
            'message' => 'error.url',
        ]));
        $metadata->addPropertyConstraint('paidAt', new Assert\Type([
            'type' => \DateTime::class,
            'message' => 'error.incorrect_type',
        ]));
        $metadata->addPropertyConstraint('expiredAt', new Assert\Type([
            'type' => \DateTime::class,
            'message' => 'error.incorrect_type',
        ]));
        $metadata->addPropertyConstraint('refund', new Assert\Valid());
    }

    /**
     * @return mixed
     */
    public function getInvoiceUuid(): UuidInterface
    {
        return $this->invoiceUuid;
    }

    /**
     * @param mixed $invoiceUuid
     */
    public function setInvoiceUuid($invoiceUuid): self
    {
        $this->invoiceUuid = $invoiceUuid;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPaymentId(): ?UuidInterface
    {
        return $this->paymentId;
    }

    /**
     * @param mixed $paymentId
     */
    public function setPaymentId($paymentId): self
    {
        $this->paymentId = $paymentId;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @param mixed $status
     */
    public function setStatus($status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @return UpdateInvoiceRequest
     */
    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCancellationDetails(): ?string
    {
        return $this->cancellationDetails;
    }

    /**
     * @param mixed $cancellationDetails
     */
    public function setCancellationDetails($cancellationDetails): self
    {
        $this->cancellationDetails = $cancellationDetails;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getInvoiceUrl(): ?string
    {
        return $this->invoiceUrl;
    }

    /**
     * @param mixed $invoiceUrl
     */
    public function setInvoiceUrl($invoiceUrl): self
    {
        $this->invoiceUrl = $invoiceUrl;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPaidAt(): ?\DateTime
    {
        return $this->paidAt;
    }

    /**
     * @param mixed $paidAt
     */
    public function setPaidAt($paidAt): self
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getExpiredAt(): ?\DateTime
    {
        return $this->expiredAt;
    }

    /**
     * @param mixed $expiredAt
     */
    public function setExpiredAt($expiredAt): self
    {
        $this->expiredAt = $expiredAt;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getRefundable(): ?bool
    {
        return $this->refundable;
    }

    public function setRefundable(bool $refundable): self
    {
        $this->refundable = $refundable;

        return $this;
    }

    public function getRefund(): RefundRequest
    {
        return $this->refund;
    }

    public function setRefund(RefundRequest $refund): self
    {
        $this->refund = $refund;

        return $this;
    }

    /**
     * @return bool
     */
    public function isCancellable(): ?bool
    {
        return $this->cancellable;
    }

    public function setCancellable(bool $cancellable): self
    {
        $this->cancellable = $cancellable;

        return $this;
    }
}
