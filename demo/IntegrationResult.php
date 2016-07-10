<?php

namespace Gentry\Gentry\Demo;

class IntegrationResult
{
    private static $bar = true;

    public static function setBar($bar)
    {
        self::$bar = $bar;
    }

    public function __construct()
    {
        $this->test =& self::$bar;
    }
}

