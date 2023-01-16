<?php

namespace App\Factory;

use App\Entity\Integration;
use App\Exception\RetailcrmApiException;
use RetailCrm\ApiClient;

class SimlaClientFactory
{
    public function create(Integration $integration)
    {
        if (!$integration->getCrmUrl() || !$integration->getCrmApiKey()) {
            throw new RetailcrmApiException('Empty Api Key');
        }

        return new ApiClient(
            $integration->getCrmUrl(),
            $integration->getCrmApiKey(),
            ApiClient::V5
        );
    }
}
