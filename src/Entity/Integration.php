<?php

namespace App\Entity;

use App\Validator\Constraints\ApiAccess;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @ORM\Entity(repositoryClass="App\Repository\IntegrationRepository")
 * @ORM\Table(
 *     name="i_integration",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="i_integration_crm_url_api_key_idx", columns={"crm_url", "crm_api_key"})
 *     }
 * )
 * @ORM\HasLifecycleCallbacks()
 */
class Integration
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
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=false, options={"default"="now()"})
     * @Serializer\Groups({"get"})
     * @Serializer\Type("DateTime<'Y-m-d H:i:s'>")
     */
    protected $createdAt;

    /**
     * @var string
     *
     * @ORM\Column(name="crm_url", type="string", length=255, nullable=false)
     * @Serializer\Groups({"get", "connect"})
     * @Serializer\Type("string")
     */
    protected $crmUrl;

    /**
     * @var string
     * @ORM\Column(name="crm_api_key", type="string", length=255, nullable=false)
     * @Serializer\Groups({"get", "connect"})
     * @Serializer\Type("string")
     */
    protected $crmApiKey;

    /**
     * @var ArrayCollection/Account[]
     *
     * @ORM\OneToMany(targetEntity="Account", mappedBy="integration")
     * @ORM\OrderBy({"id" = "ASC"})
     */
    protected $accounts;

    /**
     * @var bool
     *
     * @ORM\Column(name="active", type="boolean", options={"default"=true})
     */
    protected $active;

    /**
     * @var bool
     *
     * @ORM\Column(name="freezed", type="boolean", options={"default"=false})
     */
    protected $freeze;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new \DateTime();
        $this->accounts = new ArrayCollection();
        $this->active = false;
        $this->freeze = false;
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('crmApiKey', new Assert\NotBlank([
            'message' => 'error.not_blank',
            'groups' => ['connect', 'edit'],
        ]));
        $metadata->addPropertyConstraint('crmApiKey', new Assert\Length([
            'max' => 255,
            'maxMessage' => 'error.length',
            'groups' => ['connect', 'edit'],
        ]));
        $metadata->addPropertyConstraint('crmUrl', new Assert\Length([
            'max' => 255,
            'maxMessage' => 'error.length',
            'groups' => ['connect'],
        ]));
        $metadata->addPropertyConstraint('crmUrl', new Assert\NotBlank([
            'message' => 'error.not_blank',
            'groups' => ['connect', 'edit'],
        ]));
        $metadata->addPropertyConstraint('crmUrl', new Assert\Url([
            'message' => 'error.url',
            'groups' => ['connect', 'edit'],
        ]));
        $metadata->addConstraint(new UniqueEntity([
            'fields' => ['crmApiKey', 'crmUrl'],
            'groups' => ['edit'],
            'message' => 'error.uniq',
        ]));
        $metadata->addConstraint(new ApiAccess([
                'groups' => ['connect', 'edit'],
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

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return string
     */
    public function getCrmUrl(): ?string
    {
        return $this->crmUrl;
    }

    /**
     * @param string $crmUrl
     */
    public function setCrmUrl(?string $crmUrl): self
    {
        $this->crmUrl = rtrim($crmUrl, '/');

        return $this;
    }

    /**
     * @return string
     */
    public function getCrmApiKey(): ?string
    {
        return $this->crmApiKey;
    }

    /**
     * @param string $crmApiKey
     */
    public function setCrmApiKey(?string $crmApiKey): self
    {
        $this->crmApiKey = $crmApiKey;

        return $this;
    }

    public function getAccounts(): Collection
    {
        return $this->accounts;
    }

    public function setAccounts(Collection $accounts): self
    {
        $this->accounts = $accounts;

        return $this;
    }

    public function addAccount(Account $account): self
    {
        $this->accounts[] = $account;

        return $this;
    }

    public function removeAccount(Account $account): self
    {
        $this->accounts->removeElement($account);

        return $this;
    }

    public function getSlug(): string
    {
        return $this->getId()->toString();
    }

    public function getAccountsForCRM(): array
    {
        return $this
            ->accounts
            ->map(static function (Account $account) {
                return [
                    'code' => $account->getSlug(),
                    'name' => $account->getName(),
                    'active' => $account->isActivated(),
                ];
            })
            ->toArray();
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setFreeze(bool $freeze): self
    {
        $this->freeze = $freeze;

        return $this;
    }

    public function isFreeze(): bool
    {
        return $this->freeze;
    }

    /**
     * @Serializer\VirtualProperty
     * @Serializer\Type("boolean")
     * @Serializer\Groups({"get"})
     * @Serializer\SerializedName("isEnabled")
     */
    public function isEnabled(): bool
    {
        return !$this->freeze && $this->active;
    }
}
