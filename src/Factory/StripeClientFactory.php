<?php

namespace App\Factory;

use App\Entity\Account;
use Psr\Log\LoggerInterface;
use Stripe\Stripe;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class StripeClientFactory
{
    /**
     * @var mixed
     */
    private $stripeApiVersion;

    public function __construct(ParameterBagInterface $params)
    {
        $this->stripeApiVersion = $params->get('stripe.api_version');
    }

    public function create(Account $account, LoggerInterface $logger = null): StripeClient
    {
        if (null !== $logger) {
            Stripe::setLogger($logger);
        }

        return new StripeClient(
            [
                'api_key' => $account->getSecretKey(),
                'stripe_version' => $this->stripeApiVersion,
            ]
        );
    }
}
