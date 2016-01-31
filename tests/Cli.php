<?php

namespace Gentry\Tests;

use Gentry\Test;
use Gentry\Group;
use StdClass;
use ReflectionFunction;
use Gentry\Demo;

/**
 * Command line test running
 */
class Test
{
    /**
     * Running {0} works without problems.
     */
    public function cliTest($command = 'demo/executable')
    {
        echo 'test';
        yield 0;
    }
}

