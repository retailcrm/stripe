<?php

namespace App\Utils;

final class StringHelper
{
    public static function mbUcFirst(string $string)
    {
        return mb_strtoupper(mb_substr($string, 0, 1)) . mb_strtolower(mb_substr($string, 1));
    }
}
