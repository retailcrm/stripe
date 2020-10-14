<?php

namespace App\Tests\Callback;

use App\DataFixtures\Test\IntegrationData;
use App\Entity\Integration;
use App\Service\CRMConnectManager;
use App\Tests\BaseAppTest;
use Doctrine\ORM\EntityManagerInterface;

class ActivityTest extends BaseAppTest
{
    protected function getFixtures()
    {
        return array_merge(
            [
                new IntegrationData(true, 1),
            ]
        );
    }

    public function testCallbackWithUpdateInCRM()
    {
        $container = self::$container;
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $integration = $em->getRepository(Integration::class)->findOneBy([]);
        $checks = [
            'isActive' => $integration->isActive(),
            'isFreeze' => $integration->isActive(),
            'getCrmUrl' => $integration->getCrmUrl(),
        ];

        $client = self::getClient();

        $params = [
            'clientId' => $integration->getSlug(),
            'systemUrl' => 'http://test2.ru',
            'activity' => json_encode([
                'active' => !$checks['isActive'],
                'freeze' => !$checks['isFreeze'],
            ]),
        ];
        $this->setMockInContainer(CRMConnectManager::class, $client->getContainer(), ['sendModuleInCRM' => true]);
        $client->request('POST', '/activity', $params);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);

        $integration = $em->getRepository(Integration::class)->find($integration->getSlug());
        foreach ($checks as $field => $val) {
            $this->assertNotEquals($integration->{$field}(), $val);
        }
    }

    public function testCallbackWithoutUpdateInCRM()
    {
        $container = self::$container;
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $integration = $em->getRepository(Integration::class)->findOneBy([]);
        $checks = [
            'isActive' => $integration->isActive(),
            'isFreeze' => $integration->isActive(),
        ];

        $client = self::getClient();

        $params = [
            'systemUrl' => $integration->getCrmUrl(),
            'clientId' => $integration->getSlug(),
            'activity' => json_encode([
                'active' => !$checks['isActive'],
                'freeze' => !$checks['isFreeze'],
            ]),
        ];
        //Если будут обновляться данные в CRM, то вернется false
        $this->setMockInContainer(CRMConnectManager::class, $client->getContainer(), ['sendModuleInCRM' => false]);
        $client->request('POST', '/activity', $params);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);

        $integration = $em->getRepository(Integration::class)->find($integration->getSlug());
        foreach ($checks as $field => $val) {
            $this->assertNotEquals($integration->{$field}(), $val);
        }
    }

    public function testNotEnoughParameters()
    {
        $container = self::$container;
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $integration = $em->getRepository(Integration::class)->findOneBy([]);

        $client = self::getClient();

        $data = [
            0 => [
                'params' => [
                    'systemUrl' => $integration->getCrmUrl(),
                    'clientId' => $integration->getSlug(),
                    'activity' => json_encode([
                        'active' => true,
                        'freeze' => false,
                    ]),
                ],
                'code' => 200,
            ],
            1 => [
                'params' => [
                    'clientId' => $integration->getSlug(),
                    'activity' => json_encode([
                        'active' => true,
                        'freeze' => false,
                    ]),
                ],
                'code' => 500,
            ],
            2 => [
                'params' => [
                    'systemUrl' => $integration->getCrmUrl(),
                    'clientId' => $integration->getSlug(),
                    'activity' => json_encode([
                        'freeze' => false,
                    ]),
                ],
                'code' => 500,
            ],
            3 => [
                'params' => [
                    'systemUrl' => $integration->getCrmUrl(),
                    'clientId' => $integration->getSlug(),
                    'activity' => json_encode([
                        'active' => true,
                    ]),
                ],
                'code' => 500,
            ],
            4 => [
                'params' => [
                    'systemUrl' => $integration->getCrmUrl(),
                    'clientId' => $integration->getSlug(),
                ],
                'code' => 500,
            ],
            5 => [
                'params' => [
                    'systemUrl' => $integration->getCrmUrl(),
                    'activity' => json_encode([
                        'active' => true,
                        'freeze' => false,
                    ]),
                ],
                'code' => 500,
            ],
        ];
        //Если будут обновляться данные в CRM, то вернется false
        $this->setMockInContainer(CRMConnectManager::class, $client->getContainer(), ['sendModuleInCRM' => true]);

        foreach ($data as $dataRequest) {
            $client->request('POST', '/activity', $dataRequest['params']);
            $this->assertEquals($dataRequest['code'], $client->getResponse()->getStatusCode());
        }
    }
}
