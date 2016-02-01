<?php

namespace Gentry\Tests;

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
}

