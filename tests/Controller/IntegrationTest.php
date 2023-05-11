<?php

namespace App\Tests\Controller;

use App\DataFixtures\Test\IntegrationData;
use App\Entity\Integration;
use App\Service\ApiClientManager;
use App\Service\CRMConnectManager;
use App\Tests\AbstractApiTest;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

class IntegrationTest extends AbstractApiTest
{
    protected function getFixtures()
    {
        return array_merge(
            [
                new IntegrationData(true, 2),
            ]
        );
    }

    public function testCreateConnectValidation()
    {
        $url = self::$container->get('router')->generate('stripe_connect');
        $client = self::getClient();
        $client->disableReboot(); // Не пересоздаем ядро после каждого реквеста, чтобы не терять моки

        $this->setMockInContainer(
            ApiClientManager::class,
            $client->getContainer(),
            [
                'getCredentials' => [
                    '/api/integration-modules/{code}',
                    '/api/integration-modules/{code}/edit',
                    '/api/payment/(updateInvoice|check)',
                ],
            ]
        );

        $params = [
            'integration' => json_encode([
                'crmUrl' => 'https://msdfdfsdfdsfsdf.retailcrm.es',
                'crmApiKey' => 'sadsafdsf7dsf79sdfs',
            ]),
        ];
        $client->request(Request::METHOD_POST, $url, $params);
        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertTrue($response['success']);
        $this->assertNotEmpty($response['redirectUrl']);

        // Wrong url
        $params = [
            'integration' => json_encode([
                'crmUrl' => 'asdasdas89a89sd',
                'crmApiKey' => 'sadsafdsf7dsf79sdfs',
            ]),
        ];
        $client->request(Request::METHOD_POST, $url, $params);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(400, $client->getResponse()->getStatusCode());
        $this->assertFalse($response['success']);

        // Long key length
        $params = [
            'integration' => json_encode([
                'crmUrl' => 'https://msdfdfsdfdsfsdf.retailcrm.ru',
                'crmApiKey' => 'asd878asdasasd878asdasasd878asdasasd878asdasasd878asdasasd'
                    . '878asdasasd878asdasasd878asdasasd878asdasasd878asdasasd878asdasasd878asdasasd878asdasasd878asdasasd8'
                    . '78asdasasd878asdasasd878asdasasd878asdasasd878asdasasd878asdasasd878asdasasd878asdasasd878asdasasd8'
                    . '78asdasasd878asdasasd878asdasasd878asdasasd878asdas',
            ]),
        ];
        $client->request(Request::METHOD_POST, $url, $params);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(400, $client->getResponse()->getStatusCode());
        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid data in the request', $response['errorMsg']);
        $this->assertArrayHasKey('crmApiKey', $response['errors']);
        $this->assertCount(1, $response['errors']);
    }

    public function testSettings()
    {
        $client = self::getClient();
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Integration $integration */
        $integration = $em->getRepository(Integration::class)->findOneBy([]);
        $url = $container->get('router')->generate('stripe_get_settings', [
            'slug' => $integration->getSlug(),
        ]);
        $badUrl = $container->get('router')->generate('stripe_get_settings', [
            'slug' => Uuid::uuid4(),
        ]);
        $client->request(Request::METHOD_GET, $url);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertNotEmpty($response['integration']);

        $this->checkIntegrationFields($integration, $response);
        $this->checkNotFound($badUrl);
    }

    public function testUpdateConnectSettings()
    {
        $container = self::$container;
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var Integration[] $integrations */
        $integrations = $em->getRepository(Integration::class)->findBy([], [], 2);
        $url = $container->get('router')->generate('stripe_edit_settings', [
            'slug' => $integrations[0]->getSlug(),
        ]);

        $client = self::getClient();
        $client->disableReboot(); // Не пересоздаем ядро после каждого реквеста, чтобы не терять моки
        $this->setMockInContainer(
            CRMConnectManager::class,
            $client->getContainer(),
            ['sendModuleInCRM' => true]
        );
        $this->setMockInContainer(
            ApiClientManager::class,
            $client->getContainer(),
            [
                'getCredentials' => [
                    '/api/integration-modules/{code}',
                    '/api/integration-modules/{code}/edit',
                    '/api/payment/(updateInvoice|check)',
                ],
            ]
        );

        $newCrmApiKey = 'sadsafdsf7dsf79sdfs';

        $client->request(Request::METHOD_POST, $url, ['apiKey' => $newCrmApiKey]);

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertNotEmpty($response['integration']);

        /** @var Integration $integration */
        $integration = $em->getRepository(Integration::class)->find($integrations[0]->getSlug());
        $this->assertEquals($newCrmApiKey, $integration->getCrmApiKey());

        // Failed changing.
        $client->request(Request::METHOD_POST, $url, ['apiKey' => $integrations[1]->getCrmApiKey()]);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals(400, $client->getResponse()->getStatusCode());
        $this->assertNotEmpty($response['errorMsg']);
    }

    private function checkIntegrationFields(Integration $integration, array $response)
    {
        // Поля, которые должны быть.
        $this->assertEquals($integration->getSlug(), $response['integration']['id']);
        $this->assertEquals($integration->isEnabled(), $response['integration']['isEnabled']);
        $this->assertEquals($integration->getCrmUrl(), $response['integration']['crmUrl']);
        $this->assertEquals(
            substr_replace($integration->getCrmApiKey(), '************************', 4, -4),
            $response['integration']['crmApiKey']
        );
        $this->assertEquals($integration->getCreatedAt()->format('Y-m-d H:i:s'), $response['integration']['createdAt']);

        // Недоступные поля.
        $fields = ['accounts', 'active', 'freeze'];
        foreach ($fields as $field) {
            $this->assertArrayNotHasKey($field, $response['integration']);
        }
    }
}
