<?php

namespace Gentry\Tests;

use stdClass;
use Gentry\Demo;

/**
 * Complex test running
 */
class Complex
{
    /**
     * {0} returns true when auto-used
     */
    public function traittest(stdClass &$test = null)
    {
        if (false && version_compare(phpversion(), '7.0', '>=')) {
            $test = require '../demo/php7.php';
        } else {
            if (!class_exists('tmp_foobar')) {
                eval("class tmp_foobar extends \stdClass
                {
                    use \Gentry\Demo\TestTrait;
                }");
            }
        }
        $test = new \tmp_foobar;
        yield 'foo' => function () {
            yield true;
        };
    }

    /**
     * Integration test: first {0} returns true, then {1} returns an array with
     * count == 2, finally {2} should be true.
     */
    public function integrationTest(Demo\Integration $a, Demo\Integration $b, Demo\IntegrationResult $c)
    {
        yield 'method1' => function ($foo = true) {
            yield true;
        };
        yield 'method2' => function ($bar = true) {
            yield 'count' => 2;
        };
        yield 'test' => true;
    }
}

