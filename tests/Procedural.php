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
     * Including {0} returns "hi there", replaying {0} outputs "test" and {0}
     * will have `foo` set to "bar".
     */
    public function includeFile(SplFileInfo &$file = null)
    {
        $file = new SplFileInfo(realpath(__DIR__.'/../demo/file.php'));
        yield "hi there";
        yield 'replay' => function () {
            echo 'test';
            yield null;
        };
        yield 'foo' => 'bar';
    }
}

