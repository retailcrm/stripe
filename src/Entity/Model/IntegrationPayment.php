<?php

namespace App\Entity\Model;

class IntegrationPayment
{
    /**
     * @var array
     */
    protected $actions;

    /**
     * @var array
     */
    protected $currencies;

    /**
     * @var array
     */
    protected $shops;

    /**
     * @var array
     */
    protected $invoiceTypes;

    /**
     * @return array
     */
    public function getActions()
    {
        return $this->actions;
    }

    /**
     * @param array $actions
     *
     * @return IntegrationPayment
     */
    public function setActions($actions): self
    {
        $this->actions = $actions;

        return $this;
    }

    /**
     * @return array
     */
    public function getCurrencies()
    {
        return $this->currencies;
    }

    /**
     * @param array $currencies
     *
     * @return IntegrationPayment
     */
    public function setCurrencies($currencies): self
    {
        $this->currencies = $currencies;

        return $this;
    }

    /**
     * @return array
     */
    public function getShops()
    {
        return $this->shops;
    }

    /**
     * @param array $shops
     *
     * @return IntegrationPayment
     */
    public function setShops($shops): self
    {
        $this->shops = $shops;

        return $this;
    }

    /**
     * @return array
     */
    public function getInvoiceTypes()
    {
        return $this->invoiceTypes;
    }

    /**
     * @param array $invoiceTypes
     *
     * @return IntegrationPayment
     */
    public function setInvoiceTypes($invoiceTypes): self
    {
        $this->invoiceTypes = $invoiceTypes;

        return $this;
    }
}
