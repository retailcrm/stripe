<?php

namespace App\Tests\Mock;

class PinbaService extends \App\Service\PinbaService
{
    /**
     * @return mixed
     */
    public function timerHandler(array $tags, \Closure $handler)
    {
        return $handler();
    }
}
