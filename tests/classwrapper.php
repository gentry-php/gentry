<?php

use Gentry\Gentry\Wrapper;
use Gentry\Gentry\Logger;

class Foo
{
    public function bar(int $foo, bool $bar = null, string $foobar = '', callable $baz = null) : void
    {
    }
}
/** Tests for the class wrapper */
return function () : Generator {
    /** Test if we can wrap a class and log method calls */
    yield function () {
        $class = Wrapper::createObject(Foo::class);
        assert($class instanceof Foo);
        $class->bar(1, false, '2', function () {});
        $logged = Logger::getInstance()->getLoggedFeatures();
        assert(isset($logged['Foo']));
        assert(isset($logged['Foo']['bar']));
        assert($logged['Foo']['bar'][0] == ['int', 'bool', 'string', 'callable']);
    };
};

