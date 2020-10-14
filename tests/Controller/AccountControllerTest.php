<?php

namespace App\Tests\Controller;

use App\DataFixtures\Test\IntegrationData;
use App\Entity\Account;
use App\Entity\Integration;
use App\Service\CRMConnectManager;
use App\Service\StripeWebhookManager;
use App\Tests\AbstractApiTest;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

class AccountControllerTest extends AbstractApiTest
{
    protected function getFixtures(): array
    {
        return array_merge(
            [
                new IntegrationData(true, 1),
            ]
        );
    }

    public function testAccount()
    {
        $client = self::getClient();
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Account $account */
        $account = $em->getRepository(Account::class)->findOneBy([]);
        $url = $container->get('router')->generate('stripe_account', [
            'id' => $account->getId(),
        ]);
        // Некорректный урл.
        $badUrl = $container->get('router')->generate('stripe_account', [
            'id' => Uuid::uuid4(),
        ]);

        $client->request(Request::METHOD_GET, $url);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertNotEmpty($response['account']);

        $this->checkAccountFields($account, $response);
        $this->checkNotFound($badUrl);
    }

    public function testAdd(): void
    {
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Integration $integration */
        $integration = $em->getRepository(Integration::class)->findOneBy([]);

        $url = $container->get('router')->generate('stripe_add_account', [
            'slug' => $integration->getSlug(),
        ]);
        // Некорректный урл.
        $badUrl = $container->get('router')->generate('stripe_add_account', [
            'slug' => Uuid::uuid4(),
        ]);

        $client = self::getClient();

        $params = [
            'account' => json_encode([
                'publicKey' => 'pk_00000',
                'secretKey' => 'sk_00000',
            ]),
        ];

        $this->setMockInContainer(StripeWebhookManager::class, $client->getContainer(), [
            'subscribe' => true,
        ]);

        $client->request(Request::METHOD_POST, $url, $params);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertNotEmpty($response['account']['accountId']);

        $this->checkNotFound($badUrl, 'POST');
    }

    public function testSync(): void
    {
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Account $account */
        $account = $em->getRepository(Account::class)->findOneBy([]);

        $url = $container->get('router')->generate('stripe_sync_account', [
            'id' => $account->getId(),
        ]);
        // Некорректный урл.
        $badUrl = $container->get('router')->generate('stripe_sync_account', [
            'id' => Uuid::uuid4(),
        ]);

        $client = self::getClient();

        $this->setMockInContainer(StripeWebhookManager::class, $client->getContainer(), [
            'subscribe' => true,
            'unsubscribe' => true,
        ]);

        $client->request(Request::METHOD_GET, $url);
        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);
        /** @var Account $account */
        $account = $em->getRepository(Account::class)->findOneBy(['id' => $account->getId()]);
        $this->checkAccountFields($account, $response);

        $this->checkNotFound($badUrl);
    }

    public function testDeactivate(): void
    {
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Account $account */
        $account = $em->getRepository(Account::class)->findOneBy([]);

        $url = $container->get('router')->generate('stripe_deactivate_account', [
            'id' => $account->getId(),
        ]);
        // Некорректный урл.
        $badUrl = $container->get('router')->generate('stripe_deactivate_account', [
            'id' => Uuid::uuid4(),
        ]);

        $client = self::getClient();

        $this->setMockInContainer(CRMConnectManager::class, $client->getContainer(), [
            'sendModuleInCRM' => true,
        ]);
        $this->setMockInContainer(StripeWebhookManager::class, $client->getContainer(), [
            'unsubscribe' => true,
        ]);

        $client->request(Request::METHOD_GET, $url);
        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);
        /** @var Account $account */
        $account = $em->getRepository(Account::class)->findOneBy(['id' => $account->getId()]);
        $this->checkAccountFields($account, $response);
        $this->assertTrue($account->isDeactivated());
        $this->checkNotFound($badUrl);
    }

    public function testDeactivateModuleFail(): void
    {
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Account $account */
        $account = $em->getRepository(Account::class)->findOneBy([]);
        $url = $container->get('router')->generate('stripe_deactivate_account', [
            'id' => $account->getId(),
        ]);

        $client = self::getClient();
        $this->setMockInContainer(CRMConnectManager::class, $client->getContainer(), [
            'sendModuleInCRM' => false,
        ]);
        $this->setMockInContainer(StripeWebhookManager::class, $client->getContainer(), [
            'unsubscribe' => true,
        ]);

        $client->request(Request::METHOD_GET, $url);

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->checkSendInModuleError($response);

        /** @var Account $updAccount */
        $updAccount = $em->getRepository(Account::class)->find($account->getId());
        $this->assertSame($account->isDeactivated(), $updAccount->isDeactivated());
    }

    public function testEdit(): void
    {
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Account $account */
        $account = $em->getRepository(Account::class)->findOneBy([]);

        $this->assertFalse($account->isApproveManually());

        $url = $container->get('router')->generate('stripe_edit_account', [
            'id' => $account->getId(),
        ]);
        // Некорректный урл.
        $badUrl = $container->get('router')->generate('stripe_edit_account', [
            'id' => Uuid::uuid4(),
        ]);

        $client = self::getClient();

        $this->setMockInContainer(CRMConnectManager::class, $client->getContainer(), [
            'sendModuleInCRM' => true,
        ]);

        $isTest = $account->isTest();
        $params = [
            'account' => json_encode([
                'test' => !$isTest,
                'approveManually' => 1,
            ], JSON_PRETTY_PRINT),
        ];
        $client->request(Request::METHOD_POST, $url, $params);
        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        /** @var Account $account */
        $account = $em->getRepository(Account::class)->findOneBy(['id' => $account->getId()]);
        // Поле не должно меняться.
        $this->assertEquals($account->isTest(), $isTest);

        $this->checkAccountFields($account, $response);
        $this->checkNotFound($badUrl, Request::METHOD_POST);
    }

    public function testEditModuleFail(): void
    {
        $container = self::$container;
        $em = $container->get(EntityManagerInterface::class);

        /** @var Account $account */
        $account = $em->getRepository(Account::class)->findOneBy([]);
        $url = $container->get('router')->generate('stripe_edit_account', [
            'id' => $account->getId(),
        ]);

        $client = self::getClient();
        $this->setMockInContainer(CRMConnectManager::class, $client->getContainer(), [
            'sendModuleInCRM' => false,
        ]);

        $params = [
            'account' => json_encode([
                'approveManually' => 1,
            ], JSON_PRETTY_PRINT),
        ];
        $client->request(Request::METHOD_POST, $url, $params);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->checkSendInModuleError($response);

        /** @var Account $updAccount */
        $updAccount = $em->getRepository(Account::class)->find($account->getId());
        $this->assertSame($account->getName(), $updAccount->getName());
        $this->assertSame($account->isApproveManually(), $updAccount->isApproveManually());
    }

    private function checkAccountFields(Account $account, array $response)
    {
        // Поля, которые должны быть.
        $this->assertEquals($account->getId(), $response['account']['id']);
        $this->assertEquals($account->getName(), $response['account']['name']);
        $this->assertEquals($account->getAccountId(), $response['account']['accountId']);
        $this->assertEquals($account->isTest(), $response['account']['test']);
        $this->assertEquals($account->isApproveManually(), $response['account']['approveManually']);
        $this->assertEquals($account->getCreatedAt()->format('Y-m-d H:i:s'), $response['account']['createdAt']);

        // Недоступные поля.
        $fields = ['publicKey', 'secretKey', 'locale', 'webhooks', 'deactivatedAt', 'integration'];
        foreach ($fields as $field) {
            $this->assertArrayNotHasKey($field, $response['account']);
        }
    }
}
