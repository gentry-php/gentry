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

Each of these methods may be annotated with an `@Description`. Note this will
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
     * {0}::bar should return true
     */
    public function testSomething(Foo &$foo = null)
    {
        // Note $foo is a reference in the method declaration; we can thus
        // assign the instantiated object to it to test on.
        $foo = $this->foo;
        return true;
    }
}
```

## Using an abstract base class
If multiple tests share the same `__sleep` and `__wakeup` methods for instance,
you'll want to use a base class or a trait for that. Any class declared as
`abstract` is ignored by Gentry when checking for tests.

## Using test objects
Often you'll want a set of classes to use as mocks in your test. Just place them
in a different directory than your tests, e.g. define `tests/specs` as the
`"tests"` directory and place your mocks in `tests/mocks` and have your
autoloader handle them.

## Piping results before assertion
Using the `yield` keyword, you may "return" an array where the key is a callable
that must be satisfied, and the value is the value to expect:

```php
<?php

class MyArrayTest extends Scenario
{
    /**
     * count({0}::$bar) should equal three.
     */
    public function threeItems(Foo $foo)
    {
        yield 'count' => 3;
    }
}
```

The above test passes if `Foo::$bar` contains an array with three entries.

### Using multiple pipes
You can yield multiple callables to run multiple checks, e.g.:

```php
<?php

class MyArrayTest extends Scenario
{
    /**
     * {0}::$bar should be an array, and count({0}::$bar) must equal three.
     */
    public function threeItems(Foo $foo)
    {
        yield 'is_array' => true;
        yield 'count' => 3;
    }
}
```

The second test will only be tried if the first one succeeds, etc. So if
`$foo->bar` ends up with a non-array value, the scenario will fail and the
second test is marked as "skipped".

Note that the return values are reset for every "pipe", so technically it's more
of a filter than a pipe ;).

### Using custom functions
A key is considered callable if, well, it is, _or_ if your test object has a
callable property of the same name. This allows you to pipe custom checks, so we
could rewrite the above example as:

```php
<?php

class MyArrayTest extends Scenario
{
    /**
     * {0}::$bar should be an array, and count({0}::$bar) must equal three.
     */
    public function threeItems(Foo $foo)
    {
        $this->foocheck = function ($result) {
            return is_array($result) && count($result) == 3;
        };
        yield 'foocheck' => true;
    }
}
```

Note that the property check is done _before_ the regular `is_callable` check.
Hence, you can override built-in functions. This could also make sense if you
need to call a built-in with multiple arguments, retaining the name for clarity:

```php
<?php

class MyArrayTest extends Scenario
{
    /**
     * {0}::$bar should contain at least three items.
     */
    public function threeItems(Foo $foo)
    {
        $this->array_key_exists = function ($result) {
            return array_key_exists(2, $result);
        };
        yield 'array_key_exists' => true;
    }
}
```

For convenience, you can also directly return or yield a callable. This is
shorthand for `$this->randomName = function ($result) {};
yield 'randomName' => true;`.

### Expecting a `Closure`
If the method or property under test _expects_ a `Closure` (i.e. its return
value isn't to be piped), you could simply pipe through `is_callable` itself:

```php
<?php

class SomeTest
{
    /**
     * {0}::bar returns a callable
     */
    function mytest(Foo $foo)
    {
        yield 'is_callable' => true;
    }
}
```

### Special cases
For convenience, the following callable keys are automatically overridden on the
object under test: `is_a`, `is_subclass_of`, `method_exists`, `property_exists`.
For these special cases, the yielded value is actually the second parameter of
the function, e.g. `yield 'is_a' => 'Foo';`.

## Repeating tests
Sometimes you want the exact same test to be repeated a number of times, e.g. to
assure it returns the same result for each consecutive run. Simply mention
`"{0}::someMethod"` the desired number of times, and `yield` as many expected
results.

> Note:  `__wakeup` and `__sleep` are only called once for all iterations. If
> you specifically need to retest a method including setup > and teardown,
> declare it as non-public and add some facade methods that forward their calls.

Note that repeated tests producing output will need to match the entire output
for all iterations, concatenated.

Repeated tests are mostly useful for ensuring that something happens the exact
same way for a number of consecutive calls.
