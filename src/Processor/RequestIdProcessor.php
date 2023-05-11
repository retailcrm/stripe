<?php

namespace App\Processor;

use Ramsey\Uuid\Uuid;

class RequestIdProcessor
{
    private string $requestId;

    public function __construct()
    {
        $this->requestId = Uuid::uuid4();
    }

    public function __invoke(array $record): array
    {
        $record['extra']['request_id'] = $this->requestId;

        return $record;
    }
}
