<?php

namespace Gentry\Tests;

use Gentry\Test;
use Gentry\Group;
use StdClass;
use ReflectionFunction;
use Gentry\Demo;

/**
 * Complex test running
 */
class Complex
{
    /**
     * {0}::foo returns true when auto-used
     */
    public function traittest(\stdClass &$test = null)
    {
        if (false && version_compare(phpversion(), '7.0', '>=')) {
            $test = new class() extends \stdClass {
                use Demo\TestTrait;
            };
        } else {
            if (!class_exists('tmp_foobar')) {
                eval("class tmp_foobar extends \stdClass
                {
                    use \Gentry\Demo\TestTrait;
                }");
            }
        }
        $test = new \tmp_foobar;
        yield true;
    }

    /**
     * First {0} returns true, then {1} returns an array with count == 2,
     * finally {2}::$test should be true.
     */
    public function integrationTest(Demo\Integration $a, Demo\Integration $b, Demo\IntegrationResult $c)
    {
        yield 'method1' => function ($foo = true) {
            yield true;
        };
        yield 'method2' => function ($bar = true) {
            yield 'count' => 2;
        };
        yield true;
    }
}

