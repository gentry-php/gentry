<?php

namespace Gentry\Tests;

use Gentry\Test;
use Gentry\Demo;
use stdClass;
use ReflectionFunction;

/**
 * Basic test running
 */
class Basic
{
    /**
     * {0} should successfully run a test, pipe the result and catch trimmed output
     */
    public function testClass(Test &$test = null)
    {
        $target = new stdClass;
        $target->test = true;
        $reflection = new ReflectionFunction(
            /**
             * {0} should be true
             */
            function (stdClass &$test = null) use ($target) {
                $test = $target;
                yield 'test' => true;
            }
        );
        $test = new Test($target, $reflection);
        echo "       ";
        yield 'run' => function ($passed = 0, $failed = 0, $messages = []) {
            yield 'is_array' => true;
        };
    }

    /**
     * {0} should return true, and {0} should contain "bar"
     */
    public function testMultiple(Demo\Test $test)
    {
        yield 'test' => function () {
            yield true;
        };
        yield 'foo' => 'bar';
    }

    /**
     * {0} should output 4 spaces and return true
     */
    public function raw(Demo\Test $test)
    {
        echo '    ';
        yield 'test' => function () {
            yield true;
        };
    }
    
    /**
     * {0} should be tested statically
     */
    public function statically(Demo\Test $test = null)
    {
        yield 'aStaticMethod' => function () {
            yield true;
        };
    }
}

