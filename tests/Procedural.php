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
     * Including {0} returns 1.
     */
    public function includeFile(SplFileInfo &$file = null)
    {
        $file = new SplFileInfo('demo/file.php');
        echo 'test';
        yield 1;
    }
}

