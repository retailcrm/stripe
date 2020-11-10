<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    private $defaultLocale;

    /** @var ParameterBagInterface */
    private $params;

    public function __construct(ParameterBagInterface $params, string $defaultLocale = 'en')
    {
        $this->params = $params;
        $this->defaultLocale = $defaultLocale;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        $locale = $this->defaultLocale;
        if ($userLanguage = $request->server->get('HTTP_ACCEPT_LANGUAGE')) {
            $ln = mb_substr($userLanguage, 0, 2);
            if (in_array($ln, $this->params->get('locales'))) {
                $locale = $ln;
            }
        }

        $request->setLocale($locale);
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}
