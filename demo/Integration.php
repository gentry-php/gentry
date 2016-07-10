<?php

namespace Gentry\Gentry\Demo;

class Integration
{
    private static $callcount = 0;
    private $foo = false;

    public function method1($foo)
    {
        self::$callcount++;
        $this->foo = $foo;
        return $this->foo;
    }

    public function method2($bar)
    {
        self::$callcount++;
        IntegrationResult::setBar($bar);
        return array_fill(0, self::$callcount, true);
    }
}

