<?php

namespace Gentry\Tests;

/**
 * Command line test running
 */
class Cli
{
    /**
     * Running {0} works without problems, {1} fails.
     */
    public function cliTest($command = 'demo/executable', $command2 = 'demo/failing-executable')
    {
        echo 'test';
        yield 0;
        yield 1;
    }
}

