<?php

namespace App\EventSubscriber;

use App\Controller\LoggableController;
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

            $requestId = uniqid('', true);

            $message = $requestId . ' Incoming request: ' . $request->getMethod() . ' ' . $request->getPathInfo();

            $queryParams = $request->query->all();
            if (count($queryParams)) {
                $message .= ' with query params:  ' . json_encode($queryParams);
            }

            $params = $request->request->all();
            if (count($params)) {
                $message .= ' with params: ' . json_encode($params);
            }

            $message .= ' body ' . $request->getContent();

            $this->logger->info($message);

            $request->attributes->set('logged', true);
            $request->attributes->set('requestId', $requestId);
        }
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        if (!$event->getRequest()->attributes->get('logged')) {
            return;
        }

        $requestId = $event->getRequest()->attributes->get('requestId');

        $response = $event->getResponse();

        $message = sprintf('%s Send response with code %s and with body %s',
            $requestId,
            $response->getStatusCode(),
            $response->getContent(),
        );

        $this->logger->info($message);
    }
}
