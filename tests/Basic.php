<?php

namespace Gentry\Tests;

use Gentry\Gentry;
use Gentry\Demo;
use stdClass;
use ReflectionFunction;

/**
 * Basic test running
 */
class Basic
{
    /**
     * Test::run should successfully run a test, pipe the result and catch
     * trimmed output
    public function testClass(Gentry\Test $test)
    {
        $target = new stdClass;
        $target->test = true;
        $reflection = new ReflectionFunction(
            /**
             * Test should be true
             /
            function (stdClass &$test = null) use ($target) {
                $test = $target;
                yield assert($test->test);
            }
        );
        $test->__gentryConstruct($test, $reflection);
        echo "       ";
        $passed = 0;
        $failed = 0;
        $messages = [];
        yield assert(is_array($test->run($passed, $failed, $messages)));
    }
     */

    /**
     * 'test' should return true {?}, and 'foo' should contain "bar"
     */
    public function testMultiple(Demo\Test $test)
    {
        yield assert($test->test());
        yield assert($test->foo == 'bar');
    }
    
    /**
     * aStaticMethod should be tested statically
     */
    public function statically(Demo\Test $test = null)
    {
        yield assert($test::aStaticMethod());
    }
}

