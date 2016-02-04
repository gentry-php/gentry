# Integration tests
Integration tests test multiple features on various components, which should
remain in a consistent state - as defined by the programmer - throughout the
test. For example, a user can login, place an order, complete the payment
process, log out and back in, and see her order history with the updated item.
That's a lot of calls to make, and on a lot of different objects!

```php
<?php

class Test
{
    /**
     * {0} does something, {1} does something else and finally {2}::$fizz should
     * evaluate to `true`.
     */
    public function testTheIntegration(Foo $foo, Bar $bar, Baz $baz)
    {
        yield 'methodA' => function ($param1 = 1, $param2 = 2) {
            yield true;
        };
        yield 'methodB' => function ($param3 = 'foobar') {
            yield 'count' => 3;
        };
        yield true;
    }
}
```

Grouping-wise, you could also unit test `Foo::methodA` and `Bar::methodB` in the
same test class as your integration test, or leave them in separate test
classes. This is partly a matter of taste and partly a matter of logic; if the
unit tested method only makes sense in the context of the integration test (e.g.
`UserModel::login` probably only gets called during login and its associated
integration test) we'd advise to keep them together. If a method gets called in
multiple places (`UserModel::getAvatar` for instance) we'd probably keep it
separate in a `UserModelTest` feature.

Note that Gentry doesn't care either way (you could even test your entire
application in just one big class...) and will correctly detect which methods
still require testing no matter how you group them.

