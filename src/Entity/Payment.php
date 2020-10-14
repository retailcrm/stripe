<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PaymentRepository")
 * @ORM\Table(
 *     name="i_payment"
 * )
 */
class Payment
{
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
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default"=false})
     */
    protected $paid;

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
    protected $refundedAmount;

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
     * @var \DateTime|null
     *
     * @ORM\Column(name="expires_at", type="datetime", nullable=true)
     */
    protected $expiresAt;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="captured_at", type="datetime", nullable=true)
     */
    protected $capturedAt;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default"=false})
     */
    protected $test;

    /**
     * Фиксируем, что надо отменить платеж при переходе в статус waiting_for_capture, если хотим отменить из другого статуса.
     *
     * @var bool
     *
     * @ORM\Column(name="cancel_on_waiting_capture", type="boolean", nullable=false, options={"default"=false})
     */
    protected $cancelOnWaitingCapture;

    /**
     * @var Account
     *
     * @ORM\ManyToOne(targetEntity="Account")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id", nullable=false)
     */
    protected $account;

    /**
     * @var UuidInterface Уникальный идентфикатор crm
     *
     * @ORM\Column(name="invoice_uuid", type="uuid", nullable=false)
     */
    protected $invoiceUuid;

    /**
     * @var UuidInterface Идентфикатор для externalId в crm
     *
     * @ORM\Column(name="payment_uuid", type="uuid", nullable=false)
     */
    protected $paymentUuid;

    /**
     * @var string
     *
     * @ORM\Column(name="session_id", type="string", nullable=false)
     */
    protected $sessionId;

    /**
     * @var string|null
     *
     * @ORM\Column(name="cancellation_details", length=255, nullable=true)
     */
    protected $cancellationDetails;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default"=true})
     */
    protected $refundable;

    /**
     * @var Url
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Url", inversedBy="payments")
     * @ORM\JoinColumn(name="url_id", referencedColumnName="id", nullable=false)
     */
    protected $url;

    /**
     * @var ArrayCollection|Refund[]
     *
     * @ORM\OneToMany(targetEntity="App\Entity\Refund", mappedBy="payment")
     * @ORM\OrderBy({"createdAt" = "ASC"})
     */
    protected $refunds;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->paid = false;
        $this->test = false;
        $this->cancelOnWaitingCapture = false;
        $this->refundedAmount = 0;
        $this->refundable = true;
        $this->refunds = new ArrayCollection();
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

    public function isPaid(): bool
    {
        return $this->paid;
    }

    public function setPaid(bool $paid): self
    {
        $this->paid = $paid;

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

    public function getRefundedAmount(): ?string
    {
        return $this->refundedAmount;
    }

    public function setRefundedAmount(?string $refundedAmount): self
    {
        $this->refundedAmount = $refundedAmount;

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

    public function getExpiresAt(): ?\DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTime $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getCapturedAt(): ?\DateTime
    {
        return $this->capturedAt;
    }

    public function setCapturedAt(?\DateTime $capturedAt): self
    {
        $this->capturedAt = $capturedAt;

        return $this;
    }

    public function isTest(): bool
    {
        return $this->test;
    }

    public function setTest(bool $test): self
    {
        $this->test = $test;

        return $this;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function isCancelOnWaitingCapture(): bool
    {
        return $this->cancelOnWaitingCapture;
    }

    public function setCancelOnWaitingCapture(bool $cancelOnWaitingCapture): self
    {
        $this->cancelOnWaitingCapture = $cancelOnWaitingCapture;

        return $this;
    }

    public function getInvoiceUuid(): UuidInterface
    {
        return $this->invoiceUuid;
    }

    public function setInvoiceUuid(UuidInterface $invoiceUuid): self
    {
        $this->invoiceUuid = $invoiceUuid;

        return $this;
    }

    public function getPaymentUuid(): UuidInterface
    {
        return $this->paymentUuid;
    }

    /**
     * @return Payment
     */
    public function setPaymentUuid(UuidInterface $paymentUuid): self
    {
        $this->paymentUuid = $paymentUuid;

        return $this;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * @return Payment
     */
    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function getCancellationDetails(): ?string
    {
        return $this->cancellationDetails;
    }

    public function setCancellationDetails(?string $cancellationDetails): self
    {
        $this->cancellationDetails = $cancellationDetails;

        return $this;
    }

    public function isRefundable(): bool
    {
        return $this->refundable;
    }

    public function setRefundable(bool $refundable): self
    {
        $this->refundable = $refundable;

        return $this;
    }

    public function getUrl(): Url
    {
        return $this->url;
    }

    /**
     * @return Payment
     */
    public function setUrl(Url $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return Refund[]|ArrayCollection
     */
    public function getRefunds()
    {
        return $this->refunds;
    }

    /**
     * @return Payment
     */
    public function addRefund(Refund $refund): self
    {
        $this->refunds->add($refund);
        $refund->setPayment($this);

        return $this;
    }
}
