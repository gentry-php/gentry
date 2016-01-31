<?php

namespace Gentry\Tests;

/**
 * Command line test running
 */
class Cli
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

