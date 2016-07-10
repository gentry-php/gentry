<?php

namespace Gentry\Gentry\Tests;

use stdClass;
use Gentry\Gentry\Demo;

/**
 * Complex test running
 */
class Complex
{
    /**
     * Integration test: first method1 returns true {?}, then method2 returns an
     * array with count == 2 {?}, finally 'test' should be true {?}.
     */
    public function integrationTest(Demo\Integration $a, Demo\Integration $b, Demo\IntegrationResult $c)
    {
        yield assert($a->method1(true));
        yield assert(count($b->method2(true)) == 2);
        yield assert($c->test);
    }
}

