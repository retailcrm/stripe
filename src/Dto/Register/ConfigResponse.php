<?php

namespace App\Dto\Register;

class ConfigResponse
{
    use ApiResponseTrait;

    /**
     * @var string[]
     */
    public $scopes;

    /**
     * @var string
     */
    public $registerUrl;
}
