<?php

namespace App\EventSubscriber;

use App\Controller\LoggableController;
use App\Event\OutcomingRequestEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LoggerSubscriber implements EventSubscriberInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::RESPONSE => 'onKernelResponse',
            OutcomingRequestEvent::NAME => 'onOutcomingRequest',
        ];
    }

    public function onKernelController(ControllerEvent $event)
    {
        $controller = $event->getController();

        if (!is_array($controller)) {
            return;
        }

        if ($controller[0] instanceof LoggableController) {
            $request = $event->getRequest();

            $message = 'Incoming request: ' . $request->getMethod() . ' ' . $request->getPathInfo();

            $queryParams = $request->query->all();
            if (count($queryParams)) {
                $message .= ' with query params:  ' . json_encode($queryParams);
            }

            $params = $request->request->all();
            if (count($params)) {
                $message .= ' with params: ' . json_encode($params);
            }

            $message .= ' body ' . json_encode(json_decode($request->getContent(), true));

            $this->logger->info($message);

            $request->attributes->set('logged', true);
        }
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        if (!$event->getRequest()->attributes->get('logged')) {
            return;
        }

        $response = $event->getResponse();

        $message = sprintf('Send response with code %s and with body %s',
            $response->getStatusCode(),
            json_encode(json_decode($response->getContent(), true)),
        );

        $this->logger->info($message);
    }

    public function onOutcomingRequest(OutcomingRequestEvent $event)
    {
        $message = 'Stripe API request "' . $event->getMethod() . '", request: ' . $event->getRequest() . ', response: ' . $event->getResponse();

        $this->logger->info($message);
    }
}
