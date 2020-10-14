<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UrlRepository")
 * @ORM\Table(
 *     name="i_url",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="i_url_slug_idx", columns={"slug"})
 *     }
 * )
 */
class Url
{
    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var string
     * @ORM\Column(name="slug", type="text", nullable=false)
     */
    protected $slug;

    /**
     * @var Account
     * @ORM\ManyToOne(targetEntity="App\Entity\Account")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id", nullable=true)
     */
    protected $account;

    /**
     * @var array
     * @ORM\Column(name="request", type="json_array", nullable=true)
     */
    protected $request;

    /**
     * @var \DateTime
     * @ORM\Column(name="created_at", type="datetime", nullable=false)
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     * @ORM\Column(name="canceled_at", type="datetime", nullable=true)
     */
    protected $canceledAt;

    /**
     * @var Payment[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\Payment", mappedBy="url")
     * @ORM\OrderBy({"createdAt" = "DESC"})
     */
    protected $payments;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->payments = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * @return Url
     */
    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    /**
     * @return Url
     */
    public function setAccount(Account $account): self
    {
        $this->account = $account;

        return $this;
    }

    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * @return Url
     */
    public function setRequest(array $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * @return Url
     */
    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCanceledAt(): ?\DateTime
    {
        return $this->canceledAt;
    }

    /**
     * @param \DateTime $canceledAt
     *
     * @return Url
     */
    public function setCanceledAt(?\DateTime $canceledAt): self
    {
        $this->canceledAt = $canceledAt;

        return $this;
    }

    /**
     * @return Payment[]|ArrayCollection
     */
    public function getPayments()
    {
        return $this->payments;
    }
}
