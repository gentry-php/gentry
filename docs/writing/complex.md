# Complex tests
Of course, in a real application not all tests can be performed by injecting
classes on-the-fly. For example, reinjecting a database adapter with mock data
for every test method is tiresome, or your test may need to do a more complex
assertion than simple equality (e.g. "it returns an integer _and_ the integer
contains a value less than `10`).

## Setup and teardown
Setup (like injecting mocks) should be done in the _constructor_ of your
testclass. Likewise, teardown in the `__destroy` magic method.

For per-test setup and teardown (like loading a database fixture), use
`__wakeup` and `__sleep` respectively.

Each of these methods may be annotated with a `@Description`. Note this will
only be outputted when running in verbose mode.

For reusable objects, the following pattern is common:

```php
<?php

class MyTest
{
    public function __construct()
    {
        $this->foo = new Foo(1, 2, 3);
    }

    /**
     * {0} should return true
     */
    public function testSomething(Foo &$foo = null)
    {
        // Note $foo is a reference in the method declaration; we can thus
        // assign the instantiated object to it to test on.
        $foo = $this->foo;
        yield 'bar' => function () {
            yield true;
        };
    }
}
```

You could also define some functions for that, e.g. `getFoo`.

> Note: PHP requires `__sleep` to return an array of serializable properties.
> Since we won't be actually serializing anything, just return an empty array.
> But in most cases you'll only need `__wakeup` to reset stuff.

## Resetting global state
If you need to reset global state prior to testing (e.g. `$_POST = []`) just do
so in `__wakeup` and/or `__sleep`.

For convenience, you can call the static method
`Gentry\Test::resetAllSuperglobals`. This sets `$_GET`, `$_POST`, `$_SESSION`
and `$_COOKIE` to empty arrays (it doesn't touch `$GLOBALS` by design).

> You shouldn't really need this unless a testable method does something like
> `isset($_GET['foo'])` and you specifically want to test failure handling. For
> success handling, your test method would declare `$_GET['foo'] = 'bar';`
> anyway.

## Setup progress
If your `__wakeup` method returns an array of callables, Gentry will show a
progress indicator while the setup operation is in progress. If you don't want
or need one, just return nothing and only the description (or a default) will be
displayed.

## Using an abstract base class
If multiple tests share the same `__sleep` and `__wakeup` methods for instance,
you'll want to use a base class or a trait for that. Any class declared as
`abstract` is ignored by Gentry when checking for tests.

## Using test objects
Often you'll want a set of classes to use as mocks in your test. Just place them
in a different directory than your tests, e.g. define `tests/specs` as the
`"tests"` directory and place your mocks in `tests/mocks` and have your
autoloader handle them.

## Injecting objects with constructor arguments
Place a (public) property on your test class with the same name as the parameter
you want to inject. This should obviously be done in the constructor. Gentry
will detect that the object is "predefined" and use that instead of what you're
trying to inject.

> Note the injected object will need to satisfy the type hint specified! If it
> doesn't Gentry will fallback to attempting auto-construction.

For example, a class `Foo` requiring two constructor arguments:

```php
<?php

class MyTest
{
    public function __construct()
    {
        $this->foo = new Foo('bar', 42);
    }

    /**
     * The getNumber method should return the 42 from the constructor.
     */
    public function testIt(Foo $foo)
    {
        yield assert($foo->getNumber() == 42);
    }
}
```

If the object's constructor can be mimicked using default arguments, Gentry will
pass those for you. So this is only needed for "complex" objects with lots of
construction logic.

