<?php

namespace App\Service;

use App\Entity\Integration;
use App\Exception\RetailcrmApiException;
use Psr\Log\LoggerInterface;
use RetailCrm\ApiClient;

class ApiClientManager
{
    /**
     * @var PinbaService
     */
    private $pinbaService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(PinbaService $pinbaService, LoggerInterface $logger)
    {
        $this->pinbaService = $pinbaService;
        $this->logger = $logger;
    }

    /**
     * @throws RetailcrmApiException
     */
    public function getCredentials(Integration $integration): array
    {
        $client = $this->createClient($integration);

        $response = $this->pinbaService->timerHandler(
            [
                'api' => 'RetailCRM',
                'method' => 'credentials',
            ],
            static function () use ($client) {
                return $client->request->credentials();
            }
        );

        if (!$response->isSuccessful()) {
            throw new RetailcrmApiException($response->offsetGet('errorMsg'));
        }

        return $response->offsetGet('credentials');
    }

    /**
     * @throws RetailcrmApiException
     */
    private function createClient(Integration $integration, string $version = ApiClient::V5): ApiClient
    {
        if (!$integration->getCrmUrl() || !$integration->getCrmApiKey()) {
            throw new RetailcrmApiException('Empty Api Key');
        }

        $client = new ApiClient(
            $integration->getCrmUrl(),
            $integration->getCrmApiKey(),
            $version
        );

        $client->setLogger($this->logger);

        return $client;
    }
}
