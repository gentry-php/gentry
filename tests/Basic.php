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
    public function __construct()
    {
        $target = new stdClass;
        $target->test = true;
        $reflection = new ReflectionFunction(
            /**
             * Test should be true
             */
            function (stdClass &$test = null) use ($target) {
                $test = $target;
                yield assert($test->test);
            }
        );
        $this->function = $reflection;
        $this->test = new Test($target, $reflection);
    }

    /**
     * Test::run should successfully run a test, pipe the result and catch
     * trimmed output
     */
    public function testClass(Test &$test)
    {
        echo "       ";
        $passed = 0;
        $failed = 0;
        $messages = [];
        var_dump($test->run($passed, $failed, $messages));
        yield assert(is_array($test->run($passed, $failed, $messages)));
    }

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

