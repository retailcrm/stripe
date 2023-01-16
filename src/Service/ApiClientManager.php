<?php

namespace App\Service;

use App\Entity\Integration;
use App\Exception\RetailcrmApiException;
use App\Factory\SimlaClientFactory;

class ApiClientManager
{
    /**
     * @var PinbaService
     */
    private $pinbaService;

    /**
     * @var SimlaClientFactory
     */
    private $simlaClientFactory;

    public function __construct(
        PinbaService $pinbaService,
        SimlaClientFactory $simlaClientFactory
    ) {
        $this->pinbaService = $pinbaService;
        $this->simlaClientFactory = $simlaClientFactory;
    }

    /**
     * @throws RetailcrmApiException
     */
    public function getCredentials(Integration $integration): array
    {
        $client = $this->simlaClientFactory->create($integration);

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
