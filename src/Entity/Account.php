<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @ORM\Entity
 * @ORM\Table(
 *     name="i_account",
 *     indexes={
 *         @ORM\Index(name="i_integration_id_idx", columns={"integration_id"})
 *     }
 * )
 * @ORM\HasLifecycleCallbacks()
 */
class Account
{
    /**
     * @var UuidInterface
     *
     * @ORM\Id
     * @ORM\Column(type="uuid")
     * @Serializer\Groups({"get"})
     * @Serializer\Type("string")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Integration", inversedBy="accounts")
     * @ORM\JoinColumn(name="integration_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $integration;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=false, options={"default"="now()"})
     * @Serializer\Groups({"get"})
     * @Serializer\Type("DateTime<'Y-m-d H:i:s'>")
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="deactivated_at", type="datetime", nullable=true)
     */
    protected $deactivatedAt;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
     * @Serializer\Groups({"get"})
     * @Serializer\Type("string")
     */
    protected $name;

    /**
     * @var int|null
     *
     * @ORM\Column(name="account_id", type="string", length=255, nullable=false)
     * @Serializer\Groups({"get"})
     * @Serializer\Type("string")
     */
    protected $accountId;

    /**
     * @var bool
     *
     * @ORM\Column(name="test", type="boolean", options={"default"=false})
     * @Serializer\Groups({"get"})
     * @Serializer\Type("boolean")
     */
    protected $test;

    /**
     * @var bool
     *
     * @ORM\Column(name="approve_manually", type="boolean", options={"default"=false})
     * @Serializer\Groups({"get", "set"})
     * @Serializer\Type("boolean")
     */
    protected $approveManually;

    /**
     * @var string
     *
     * @ORM\Column(name="public_key", type="string", length=255, nullable=true)
     * @Serializer\Groups({"set"})
     * @Serializer\Type("string")
     */
    protected $publicKey;

    /**
     * @var string
     *
     * @ORM\Column(name="secret_key", type="string", length=255, nullable=true)
     * @Serializer\Groups({"set"})
     * @Serializer\Type("string")
     */
    protected $secretKey;

    /**
     * @var string
     *
     * @ORM\Column(name="locale", type="string", length=10, nullable=false, options={"default"="en_US"})
     */
    protected $locale;

    /**
     * @ORM\OneToMany(targetEntity="StripeWebhook", mappedBy="account")
     */
    protected $webhooks;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new \DateTime();
        $this->approveManually = false;
        $this->test = false;
        $this->locale = 'en_US';
        $this->webhooks = new ArrayCollection();
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('name', new Assert\NotBlank([
            'message' => 'error.account.name.not_blank',
        ]));
        $metadata->addPropertyConstraint('name', new Assert\Length([
            'max' => 255,
            'maxMessage' => 'error.account.name.length',
        ]));
        $metadata->addConstraint(new UniqueEntity([
            'fields' => ['integration', 'accountId', 'deactivatedAt'],
            'message' => 'error.account.uniq',
            'ignoreNull' => false,
        ]));
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * @return Account
     */
    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @Serializer\VirtualProperty
     * @Serializer\Type("boolean")
     * @Serializer\Groups({"get"})
     */
    public function isDeactivated(): bool
    {
        return $this->deactivatedAt instanceof \DateTime;
    }

    /**
     * @Serializer\VirtualProperty
     * @Serializer\Type("boolean")
     * @Serializer\Groups({"get"})
     */
    public function isActivated(): bool
    {
        return null === $this->deactivatedAt;
    }

    /**
     * @return Account
     */
    public function setDeactivatedAt(?\DateTime $deactivatedAt): self
    {
        $this->deactivatedAt = $deactivatedAt;

        return $this;
    }

    public function getAccountId(): ?string
    {
        return $this->accountId;
    }

    public function setAccountId(?string $accountId): self
    {
        $this->accountId = $accountId;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function isApproveManually(): bool
    {
        return $this->approveManually;
    }

    public function setApproveManually(bool $approveManually): self
    {
        $this->approveManually = $approveManually;

        return $this;
    }

    public function getIntegration(): Integration
    {
        return $this->integration;
    }

    public function setIntegration(Integration $integration): self
    {
        $this->integration = $integration;

        return $this;
    }

    /**
     * @return string
     */
    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    public function setPublicKey(string $publicKey): self
    {
        $this->publicKey = $publicKey;

        return $this;
    }

    /**
     * @return string
     */
    public function getSecretKey(): ?string
    {
        return $this->secretKey;
    }

    public function setSecretKey(string $secretKey): self
    {
        $this->secretKey = $secretKey;

        return $this;
    }

    /**
     * @return ArrayCollection|StripeWebhook[]
     */
    public function getWebhooks()
    {
        return $this->webhooks;
    }

    /**
     * @param $webhooks
     */
    public function setWebhooks($webhooks): self
    {
        $this->webhooks = $webhooks;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->getId()->toString();
    }
}
