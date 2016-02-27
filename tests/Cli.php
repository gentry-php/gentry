<?php

namespace Gentry\Tests;

/**
 * Command line test running
 */
class Cli
{
    /**
     * Running first script works without problems {?} and echoes "test" {?},
     * the second one fails {?}.
     */
    public function cliTest()
    {
        ob_start();
        passthru(__DIR__.'/../demo/executable', $return);
        $out = ob_get_clean();
        yield assert($return == 0);
        yield assert($out == 'test');
        passthru(__DIR__.'/../demo/failing-executable', $return);
        yield assert($return == 1);
    }
}

