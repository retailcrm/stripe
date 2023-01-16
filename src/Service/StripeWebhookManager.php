<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\StripeWebhook;
use App\Factory\StripeClientFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StripeWebhookManager
{
    public const WEBHOOK_ROUTE = 'stripe_hooks';

    private StripeClientFactory $stripeClientFactory;
    private EntityManagerInterface $em;
    private UrlGeneratorInterface $urlGenerator;
    private string $stripeApiVersion;
    private string $host;

    public function __construct(
        StripeClientFactory $stripeClientFactory,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator,
        ParameterBagInterface $params
    ) {
        $this->em = $em;
        $this->urlGenerator = $urlGenerator;
        $this->host = $params->get('domain');
        $this->stripeApiVersion = $params->get('stripe.api_version');
        $this->stripeClientFactory = $stripeClientFactory;
    }

    /**
     * @throws \Exception
     */
    public function subscribe(Account $account)
    {
        $context = $this->urlGenerator->getContext();
        $context
            ->setScheme('https')
            ->setHost($this->host)
        ;

        $stripe = $this->stripeClientFactory->create($account);

        $enabledEvents = [
            'payment_intent.amount_capturable_updated',
            'payment_intent.canceled',
            'payment_intent.succeeded',
            'charge.refunded',
        ];

        $url = $this->urlGenerator->generate(
            self::WEBHOOK_ROUTE,
            ['id' => $account->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $endpoint = $stripe->webhookEndpoints->create([
            'url' => $url,
            'api_version' => $this->stripeApiVersion,
            'enabled_events' => $enabledEvents,
            'description' => 'RetailCRM',
            'metadata' => [
                'created_by' => 'RetailCRM payment module',
            ],
        ]);

        if ($endpoint) {
            $webhook = new StripeWebhook();
            $webhook
                ->setAccount($account)
                ->setWebhook($endpoint->id)
                ->setSecret($endpoint->secret)
                ->setUrl($url);

            $this->em->persist($webhook);
            $this->em->flush();
        }
    }

    /**
     * @throws \Exception
     */
    public function unsubscribe(Account $account): void
    {
        if ($account->getWebhooks()->isEmpty()) {
            return;
        }

        $stripe = $this->stripeClientFactory->create($account);

        foreach ($account->getWebhooks() as $webhook) {
            try {
                $stripe->webhookEndpoints->delete($webhook->getWebhook(), []);
            } catch (\Exception $e) {
                // @TODO Если вебхук был отключен
            } finally {
                $this->em->remove($webhook);
            }
        }
    }
}
