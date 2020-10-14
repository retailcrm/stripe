<?php

namespace App\Service;

class PinbaService
{
    /**
     * @return mixed
     */
    public function timerHandler(array $tags, \Closure $handler)
    {
        $timer = pinba_timer_start($tags);
        $response = $handler();
        pinba_timer_stop($timer);

        return $response;
    }
}
