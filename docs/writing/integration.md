# Integration tests
Gentry's basic premise is that each scenario tests exactly one method on one
object. This works great for classical unit tests, but what if you want to run
something more akin to an _integration test_?

Integration tests test multiple features on various components, which should
remain in a consistent state - as defined by the programmer - throughout the
test. For example, a user can login, place an order, complete the payment
process, log out and back in, and see her order history with the updated item.
That's a lot of calls to make, and on a lot of different objects!

One could accomplish this by placing these test in a seperate scenario class and
doing setup/teardown in its constructor instead of in `__wakeup`/`__sleep`, but
luckily Gentry offers an easier way which is more descriptive (after all, it
won't be immediately apparent to the casual reader that the tests are related).
You can also use the following syntax:

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

Whoah, what's going on here?

First, we left out the method names in our doccomment for `{0}` and `{1}`. Next,
we used the "pipe style" to yield key/value pairs of method names and callables.
Gentry recognises when the pipe is an existing method on one of the injected
objects, _and_ its value is itself a callable. In these cases, the method in
question will be called using the parameters defined in the callable (instead of
taking parameters from the test method itself). Finally, the callable yields the
expected result - optionally via a pipe - of calling that method. Inside the
callable, you can yield multiple pipes; the singular result is checked on all of
them without re-calling the method. To recall the method (e.g. `methodB` in the
above example) mention multiple `{n}` items for that parameter and simply yield
multiple callables with that method name as normally.

Of course, the callables can have referenced arguments too which are first
"massaged" before yielding. They can also be shared using the `use (&$varname)`
syntax.

## Testing related properties in an integration
Note that the `yield "$someProperty" => function () { ... }` syntax doesn't make
sense and hence isn't supported. If you need to test properties of > related
objects, simply write `{n}::$property` in your description:

```php
<?php

class Test
{
    /**
     * {0} does something, so does {1} and afterwards {0}::$foo is true.
     */
    public function anotherIntegrationTest(Foo $foo, Bar $bar)
    {
        yield 'methodA' => function () {
            yield true;
        };
        yield 'methodB' => function () {
            yield true;
        };
        yield true;
    }
}
```

