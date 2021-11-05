<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

class Functions
{
    public static function strEndsWith(string $haystack, string $needle): bool
    {
        return $needle !== '' ? substr($haystack, -strlen($needle)) === $needle : true;
    }
}
