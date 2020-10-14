<?php

namespace App\Tests\Controller;

use App\DataFixtures\Test\IntegrationData;
use App\DataFixtures\Test\PaymentData;
use App\Entity\Payment;
use App\Repository\PaymentRepository;
use App\Service\StripeManager;
use App\Tests\BaseAppTest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ShortControllerTest extends BaseAppTest
{
    protected function getFixtures(): array
    {
        return array_merge(
            parent::getFixtures(),
            [
                new IntegrationData(true, 1),
                new PaymentData(true, 4),
            ]
        );
    }

    public function testIndex(): void
    {
        $container = self::$container;

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var RouterInterface $router */
        $router = $container->get(RouterInterface::class);
        /** @var TranslatorInterface $translator */
        $translator = $container->get(TranslatorInterface::class);

        $client = self::getClient();

        /** @var PaymentRepository $paymentRepository */
        $paymentRepository = $em->getRepository(Payment::class);

        /** @var Payment $pendingPayment */
        $pendingPayment = $paymentRepository->findOneBy(['status' => StripeManager::STATUS_PAYMENT_PENDING]);

        $crawler = $client->request('GET', $router->generate('short_url', ['slug' => $pendingPayment->getUrl()->getSlug()]));
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertEquals($crawler->filter('.description')->html(), $translator->trans('loader.wait'));

        /** @var Payment $canceledPayment */
        $canceledPayment = $paymentRepository->findOneBy(['status' => StripeManager::STATUS_PAYMENT_CANCELED]);
        $canceledPayment->getUrl()->setCanceledAt($canceledPayment->getCreatedAt());
        $em->flush();
        $crawler = $client->request('GET', $router->generate('short_url', ['slug' => $canceledPayment->getUrl()->getSlug()]));
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertEquals($crawler->filter('.description')->html(), $translator->trans('loader.cancel'));

        /** @var Payment $succeededPayment */
        $succeededPayment = $paymentRepository->findOneBy(['status' => StripeManager::STATUS_PAYMENT_SUCCEEDED]);
        $crawler = $client->request('GET', $router->generate('short_url', ['slug' => $succeededPayment->getUrl()->getSlug()]));
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertEquals($crawler->filter('.description')->html(), $translator->trans('loader.payment'));

        /** @var Payment $waitingPayment */
        $waitingPayment = $paymentRepository->findOneBy(['status' => StripeManager::STATUS_PAYMENT_WAITING_CAPTURE]);
        $crawler = $client->request('GET', $router->generate('short_url', ['slug' => $waitingPayment->getUrl()->getSlug()]));
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertEquals($crawler->filter('.description')->html(), $translator->trans('loader.payment'));
    }
}
