<?php

namespace App\Tests\Controller;

use App\DataFixtures\Test\IntegrationData;
use App\Entity\Integration;
use App\Factory\ApiClientFactory;
use App\Service\ApiClientManager;
use App\Service\CRMConnectManager;
use App\Service\RegisterService;
use App\Tests\BaseAppTest;
use RetailCrm\ApiClient;
use RetailCrm\Client\ApiVersion5;
use RetailCrm\Response\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class RegisterControllerTest extends BaseAppTest
{
    protected $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
    }

    protected function getFixtures()
    {
        return array_merge(
            [
                new IntegrationData(true, 1),
            ]
        );
    }

    public function testConfig(): void
    {
        $this->client->request('GET', '/simple/connect');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertNotEmpty($this->client->getResponse()->getContent());
    }

    public function testRegisterInvalidToken(): void
    {
        $this->client->request('POST', '/simple/connect', [
            'register' => json_encode([
                'token' => 'invalid',
                'apiKey' => 'apiKey',
                'systemUrl' => 'systemUrl',
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertNotEmpty($this->client->getResponse()->getContent());
        self::assertEquals(json_encode([
            'accountUrl' => null,
            'success' => false,
            'errorMsg' => 'Invalid token',
        ]), $this->client->getResponse()->getContent());
    }

    public function testRegisterExisting(): void
    {
        $oldIntegration = $this->getConnection();
        $newApiKey = substr($oldIntegration->getCrmApiKey(), 0, strlen($oldIntegration->getCrmApiKey()) - 4) . '_new';

        $this->setMockInContainer(CRMConnectManager::class, $this->client->getContainer(), ['sendModuleInCRM' => true]);
        $this->setMockInContainer(
            ApiClientManager::class,
            $this->client->getContainer(),
            [
                'getCredentials' => [
                    '/api/integration-modules/{code}',
                    '/api/integration-modules/{code}/edit',
                    '/api/payment/(updateInvoice|check)',
                ],
            ]
        );

        $this->client->request('POST', '/simple/connect', [
            'register' => json_encode([
                'token' => $this->generateToken($newApiKey),
                'apiKey' => $newApiKey,
                'systemUrl' => $this->getConnection()->getCrmUrl(),
            ], JSON_THROW_ON_ERROR
            ),
        ]);

        $newIntegration = $this->getConnection();

        self::assertEquals($newApiKey, $newIntegration->getCrmApiKey());
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertEquals(
            json_encode([
                'accountUrl' => $this->getAccountUrl($newIntegration),
                'success' => true,
                'errorMsg' => null,
            ]), $this->client->getResponse()->getContent()
        );
    }

    public function testRegisterFailure(): void
    {
        $response = new ApiResponse(
            404,
            json_encode([
                'success' => false,
                'errorMsg' => 'Wrong "apiKey" value.',
            ])
        );

        $apiClientRequest = $this->createMock(ApiVersion5::class);
        $apiClientRequest->method('credentials')->willReturn($response);

        $apiClient = new ApiClient('https://newaccount-5.simla.com', 'newaccount-5');
        $apiClient->request = $apiClientRequest;

        $restClientFactory = $this->createMock(ApiClientFactory::class);
        $restClientFactory->method('create')->willReturn($apiClient);

        $this->client->getContainer()->set(ApiClientFactory::class, $restClientFactory);

        $this->client->request('POST', '/simple/connect', [
            'register' => json_encode(
                [
                    'token' => $this->generateToken(
                        'newaccount-5'
                    ),
                    'apiKey' => 'newaccount-5',
                    'systemUrl' => 'https://newaccount-5.simla.com',
                ], JSON_THROW_ON_ERROR
            ),
        ]);

        self::assertNull(
            $this->client->getContainer()
                ->get('doctrine.orm.entity_manager')
                ->getRepository(Integration::class)
                ->findOneBy(['crmApiKey' => 'newaccount-5'])
        );
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertEquals(
            json_encode([
                'accountUrl' => null,
                'success' => false,
                'errorMsg' => 'Invalid request data',
            ]), $this->client->getResponse()->getContent()
        );
    }

    public function testRegister()
    {
        $this->setMockInContainer(CRMConnectManager::class, $this->client->getContainer(), ['sendModuleInCRM' => true]);
        $this->setMockInContainer(
            ApiClientManager::class,
            $this->client->getContainer(),
            [
                'getCredentials' => [
                    '/api/integration-modules/{code}',
                    '/api/integration-modules/{code}/edit',
                    '/api/payment/(updateInvoice|check)',
                ],
            ]
        );

        $this->client->request('POST', '/simple/connect', [
            'register' => json_encode(
                [
                    'token' => $this->generateToken(
                        'ffqfNWKTDvdaqwcPgQhhHEEutO81Qbff'
                    ),
                    'apiKey' => 'ffqfNWKTDvdaqwcPgQhhHEEutO81Qbff',
                    'systemUrl' => 'https://newaccount.simla.com',
                ], JSON_THROW_ON_ERROR
            ),
        ]);

        /** @var Integration $newIntegration */
        $newIntegration = $this->client->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository(Integration::class)
            ->findOneBy(['crmApiKey' => 'ffqfNWKTDvdaqwcPgQhhHEEutO81Qbff']);

        self::assertNotNull($newIntegration);
        self::assertEquals($newIntegration->getCrmUrl(), 'https://newaccount.simla.com');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertEquals(
            json_encode([
                'accountUrl' => $this->getAccountUrl($newIntegration),
                'success' => true,
                'errorMsg' => null,
            ]), $this->client->getResponse()->getContent()
        );
    }

    private function generateToken(string $apiKey): string
    {
        $getEnv = new \ReflectionMethod($this->client->getContainer(), 'getEnv');
        $getEnv->setAccessible(true);

        $secret = $getEnv->invoke($this->client->getContainer(), 'ONE_STEP_CONNECTION_SECRET');

        return hash_hmac('sha256', $apiKey, $secret);
    }

    private function getAccountUrl(Integration $integration): string
    {
        return $this->client->getContainer()->get(RegisterService::class)->getAccountUrl($integration);
    }
}
