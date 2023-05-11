<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class OutcomingRequestEvent extends Event
{
    public const NAME = 'request.outcome';
    private string $method;
    private string $request;
    private string $response;

    public function __construct(string $method, array $request, array $response)
    {
        $this->method = $method;
        $this->request = json_encode($request);
        $this->response = json_encode($response);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getRequest(): string
    {
        return $this->request;
    }

    public function getResponse(): string
    {
        return $this->response;
    }
}
