<?php

namespace Gentry\Tests;

use SplFileInfo;

/**
 * Procedural function and code checking
 */
class Procedural
{
    /**
     * Calling demo procedure with 42 returns 21.
     */
    public function procedure(callable $function)
    {
        yield assert($function('Gentry\Demo\procedure', 42) == 21);
    }

    /**
     * Including returns "hi there" {?}, and $foo exists afterwards {?}.
     */
    public function includeFile(SplFileInfo &$file = null)
    {
        yield assert('hi there' == include __DIR__.'/../demo/file.php');
        yield assert($foo == 'bar');
    }
}

