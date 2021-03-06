<?php

namespace App\Tests;

use App\Entity\Integration;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class BaseAppTest extends WebTestCase
{
    protected function setUp()
    {
        self::bootKernel();
        $this->loadFixtures($this->getFixtures());
    }

    protected function getFixtures()
    {
        return [];
    }

    protected static function getClient()
    {
        self::ensureKernelShutdown();

        return static::createClient();
    }

    protected function loadFixtures(array $fixtures = [])
    {
        $loader = new Loader();

        foreach ($fixtures as $fixture) {
            if (!\is_object($fixture)) {
                $fixture = new $fixture();
            }

            if ($fixture instanceof ContainerAwareInterface) {
                $fixture->setContainer(self::$container);
            }

            $loader->addFixture($fixture);
        }

        $em = $this->getEntityManager();

        $purger = new ORMPurger($em);
        $executor = new ORMExecutor($em, $purger);
        $executor->execute($loader->getFixtures());
    }

    protected function setMockInContainer($containerId, ContainerInterface $container, array $methodsWithReturns)
    {
        $mockCRMConnectManager = $this->getMockBuilder($containerId)->disableOriginalConstructor()->getMock();
        foreach ($methodsWithReturns as $method => $return) {
            if ($return instanceof Stub) {
                $mockCRMConnectManager->method($method)->will($return);

                continue;
            }

            $mockCRMConnectManager->method($method)->willReturn($return);
        }
        $container->set($containerId, $mockCRMConnectManager);
    }

    protected static function createClient(array $options = [], array $server = [])
    {
        self::ensureKernelShutdown();

        return parent::createClient($options, $server);
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return self::$container->get(EntityManagerInterface::class);
    }

    protected function getConnection(): Integration
    {
        return $this->getEntityManager()
            ->getRepository(Integration::class)
            ->findOneBy(['crmUrl' => 'https://demo.retailcrm.ru']);
    }
}
