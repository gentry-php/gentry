<?php

namespace Gentry\Tests;

use Gentry\Scenario;
use Gentry\Demo;

/**
 * @Description The demo should pass all tests.
 */
class DemoTest extends Scenario
{
    /**
     * @Description "something" should always return true
     */
    public function something(Demo\DemoClass $demo, $something)
    {
        return true;
    }
}

