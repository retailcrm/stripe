<?php

namespace App\Entity\Model;

use App\Service\CRMConnectManager;

class IntegrationModule
{
    protected $code;
    protected $integrationCode;
    protected $active;
    protected $name;
    protected $logo;
    protected $clientId;
    protected $baseUrl;
    protected $actions;
    protected $availableCountries;
    protected $accountUrl;
    protected $integrations;

    /**
     * @param array $availableCountries
     */
    public function __construct($availableCountries = [])
    {
        $this->setAvailableCountries($availableCountries);
        $this->setCode(CRMConnectManager::MODULE_CODE);
        $this->setIntegrationCode(CRMConnectManager::MODULE_CODE);
    }

    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @return IntegrationModule
     */
    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getIntegrationCode(): string
    {
        return $this->integrationCode;
    }

    /**
     * @return IntegrationModule
     */
    public function setIntegrationCode(string $integrationCode): self
    {
        $this->integrationCode = $integrationCode;

        return $this;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    /**
     * @return IntegrationModule
     */
    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return IntegrationModule
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLogo()
    {
        return $this->logo;
    }

    /**
     * @return IntegrationModule
     */
    public function setLogo(string $logo): self
    {
        $this->logo = $logo;

        return $this;
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
     * @return IntegrationModule
     */
    public function setClientId($clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @param mixed $baseUrl
     *
     * @return IntegrationModule
     */
    public function setBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * @param mixed $actions
     *
     * @return IntegrationModule
     */
    public function setActions(array $actions): self
    {
        $this->actions = $actions;

        return $this;
    }

    public function getAvailableCountries(): ?array
    {
        return $this->availableCountries;
    }

    /**
     * @param mixed $availableCountries
     *
     * @return IntegrationModule
     */
    public function setAvailableCountries(array $availableCountries): self
    {
        $this->availableCountries = $availableCountries;

        return $this;
    }

    public function getAccountUrl(): string
    {
        return $this->accountUrl;
    }

    /**
     * @return IntegrationModule
     */
    public function setAccountUrl(string $accountUrl): self
    {
        $this->accountUrl = $accountUrl;

        return $this;
    }

    public function getIntegrations(): ?array
    {
        return $this->integrations;
    }

    /**
     * @return IntegrationModule
     */
    public function setIntegrations(array $integrations): self
    {
        $this->integrations = $integrations;

        return $this;
    }
}
