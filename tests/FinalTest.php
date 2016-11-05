<?php

namespace Gentry\Tests;

use Gentry\Demo\FinalClass;

/**
 * Tests for classes marked as "final".
 */
class FinalTest
{
    /**
     * When injecting a class marked as "final", we should receive the actual
     * object, not a wrapped one. We can then manually wrap it {?} and the
     * method call gets logged as expected {?}.
     */
    public function finalClassGetsInjected(FinalClass $final)
    {
        yield assert(get_class($final) == 'Gentry\Demo\FinalClass');
        $wrapped = \Gentry\Gentry\Test::createWrappedObject(new \ReflectionClass($final), $final);
        yield assert(!($wrapped instanceof $final));
        $test = $wrapped->foo();
        $logged = \Gentry\Gentry\Logger::getInstance()->getLoggedFeatures();
        yield assert(isset($logged['Gentry\Demo\FinalClass']) && isset($logged['Gentry\Demo\FinalClass']['foo']));
    }
}

