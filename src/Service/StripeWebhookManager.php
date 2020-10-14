<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\StripeWebhook;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StripeWebhookManager
{
    public const WEBHOOK_ROUTE = 'stripe_hooks';

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var string
     */
    private $host;

    public function __construct(EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ParameterBagInterface $params)
    {
        $this->em = $em;
        $this->urlGenerator = $urlGenerator;
        $this->host = $params->get('domain');
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

        $stripe = new \Stripe\StripeClient($account->getSecretKey());

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

        $stripe = new \Stripe\StripeClient($account->getSecretKey());

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
