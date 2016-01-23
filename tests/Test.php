<?php

namespace Gentry\Tests;

use Gentry\Test;
use Gentry\Group;
use StdClass;
use ReflectionFunction;
use Gentry\Demo;

/**
 * @Feature Basic test running
 */
class Test
{
    /**
     * @Scenario {0}::run should successfully run a test, pipe the result and catch trimmed output
     * @Pipe is_array
     */
    public function testClass(Test &$test = null, $passed = 0, $failed = 0)
    {
        $target = new StdClass;
        $target->test = true;
        $reflection = new ReflectionFunction(
            /**
             * @Scenario {0}::$test should be true
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
        return true;
    }

    /**
     * @Scenario {0}::run should successfully run a group of tests, pipe the result and catch trimmed output
     */
    public function grouping(Group &$group = null, $passed = 0, $failed = 0)
    {
        $target = new StdClass;
        $inject = new StdClass;
        $inject->foo = 'foo';
        $inject->bar = 'bar';
        $group = new Group($target, $inject, [
            /**
             * @Scenario {0}::$foo should contain foo
             */
            function () { return 'foo'; },
            /**
             * @Scenario {0}::$bar should contain bar
             */
            function () { return 'bar'; },
        ]);
        \Gentry\out("  * <blue>stdClass::\$foo should contain foo");
        \Gentry\out(" <green>[OK]\n");
        \Gentry\out("  * <blue>stdClass::\$bar should contain bar");
        \Gentry\out(" <green>[OK]\n");
    }

    /**
     * @Scenario {0}::test should output 4 spaces and return true
     * @Raw
     */
    public function raw(Demo\Test $test)
    {
        echo '    ';
        return true;
    }
}

