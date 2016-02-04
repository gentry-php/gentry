<?php

namespace Gentry\Tests;

use SplFileInfo;

/**
 * Procedural function and code checking
 */
class Procedural
{
    /**
     * Calling {0} with true returns true.
     */
    public function procedure(callable &$function = null)
    {
        $function = '\Gentry\Demo\procedure';
        yield function ($arg = true) {
            yield true;
        };
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

