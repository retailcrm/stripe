<?php

namespace App\Service;

use App\Entity\Integration;
use App\Exception\RetailcrmApiException;
use App\Factory\ApiClientFactory;

class ApiClientManager
{
    /**
     * @var PinbaService
     */
    private $pinbaService;

    /**
     * @var ApiClientFactory
     */
    private $apiClientFactory;

    public function __construct(
        PinbaService $pinbaService,
        ApiClientFactory $apiClientFactory
    ) {
        $this->pinbaService = $pinbaService;
        $this->apiClientFactory = $apiClientFactory;
    }

    /**
     * @throws RetailcrmApiException
     */
    public function getCredentials(Integration $integration): array
    {
        $client = $this->apiClientFactory->create($integration);

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
}
