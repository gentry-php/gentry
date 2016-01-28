<?php

namespace Gentry\Tests;

use Gentry\Test;
use Gentry\Group;
use StdClass;
use ReflectionFunction;
use Gentry\Demo;

/**
 * Basic test running
 */
class Test
{
    /**
     * {0}::run should successfully run a test, pipe the result and catch trimmed output
     */
    public function testClass(Test &$test = null, $passed = 0, $failed = 0, $messages = [])
    {
        $target = new StdClass;
        $target->test = true;
        $reflection = new ReflectionFunction(
            /**
             * {0}::$test should be true
             */
            function (StdClass &$test = null) use ($target) {
                $test = $target;
                return true;
            }
        );
        $test = new Test($target, $reflection);
        \Gentry\out("  * <blue>stdClass::\$test should be true");
        \Gentry\out(" <green>[OK]\n");
        echo "       ";
        return 'is_array';
    }

    /**
     * {0}::test should return true, and {0}::$foo should contain "bar"
     */
    public function testMultiple(Demo\Test $test)
    {
        yield true;
        yield 'bar';
    }

    /**
     * {0}::test should output 4 spaces and return true
     */
    public function raw(Demo\Test $test)
    {
        echo '    ';
        return true;
    }
    
    /**
     * {0}::aStaticMethod should be tested statically
     */
    public function statically(Demo\Test $test = null)
    {
        return true;
    }
}

