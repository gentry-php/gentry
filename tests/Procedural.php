<?php

namespace Gentry\Tests;

use SplFileInfo;

/**
 * Procedural function and code checking
 */
class Procedural
{
    /**
     * Calling {0} works with true returns true.
     */
    public function procedure(callable &$function = null, $foo = true)
    {
        $function = '\Gentry\Demo\procedure';
        yield true;
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

