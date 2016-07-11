# Writing tests for Gentry

## The basics
Each testable feature should be placed in your `"tests"` directory or one of its
subdirectories (Gentry recurses for you) You can (optionally) describe the
feature by adding a docblock to the class:

```php
<?php

/**
 * This is going to explain how this should work
 */
class MyFirstTest
{
}
```

The description is just for clarity, it doesn't itself do anything. A test class
is referred to as a "feature". Though theoretically you could place all your
tests in one big class, it makes sense to group related tests into one class.
These classes should completely describe a "feature". Exactly how you group is a
matter of taste and will depend on your application.

Let's assume we want to test a class `Foo` with a method `bar`. The method for
now simply always returns `true`:

```php
<?php

class Foo
{
    public function bar()
    {
        return true;
    }
}
```

> This file then resides in your `"src"` directory.

Add a _public_ method to your test class. It can have any name, but its
parameters *must* be the type-hinted thing(s) we are going to test. Gentry
assumes all a test class's public methods run tests, and you only need to test
public methods on your classes. These public test methods are referred to as
"scenarios".

> Obviously, to write helper methods, simply declare them `protected` or
> `private`.

Add a docblock to the new public method which describes exactly what the scenario
is going to test.

```php
<?php

// ...
    /**
     * Bar should always return true
     */
    public function bar(Foo $foo)
    {
    }
```

The `$foo` parameter gets injected automatically as a `new Foo`. We'll see later
on how to inject objects requiring e.g. constructor arguments.

Finally, to make the test actually _test_ something, we need to turn the test
method into a `Generator`. Simply yield assertions of all the things you need to
test for that particular object:

```php
<?php

// ...
        yield assert($foo->bar()); // Returns true, so success!
        yield assert($foo->bar() != false); // Again, this passed.
        yield assert(is_bool($foo->bar())); // Still good.
        yield assert(!$foo->bar()); // This causes the test to fail.
// ...
```

Run the Gentry executable. Its output will include something like:

```
* Bar should always return true [OK] [OK] [OK] [FAILED]
```

That makes sense, we specifically wanted the last test to fail to see what
happens. You can remove the fourth assertion and run again to see all tests
pass.

## Adding breakpoints
We end up with three scenarios in the previous test, so typically we'd want to
describe all of them in our docblock. E.g.:

```php
<?php

// ...
        /**
         * 'bar' returns true when called normally, and true ain't false. We
         * also assert that it actually returns a bool (not something truthy).
         */
// ...
```

It would be nice to see the `[OK]` markers where we logically expect them in the
output. Something like `'bar' returns true when called normally [OK], ...`. To
accomplish this, we add "breakpoints":

```php
<?php

// ...
        /**
         * 'bar' returns true when called normally {?}, and true ain't false
         * {?}. We also assert that it actually returns a bool (no something
         * truthy) {?}.
         */
// ...
```

You can think of the `{?}` breakpoint notation as saying "ok Gentry, tell me
what you got so far". If the number of breakpoints exceeds the number of yields
in your test you'll simply not see part of your description and Gentry will warn
you that more tests were expected. Conversely, yields after Gentry "runs out" of
breakpoints are simply appended (like before we even had breakpoints). Gentry
assumes your description is just crappy :)

## Testing multiple related objects
You can inject as many things into your test as you need. It makes sense to
only inject "related" stuff, but Gentry doesn't attempt to validate that.

```php
<?php

// ...
/**
 * Foo::bar should be true {?}, and Bar::fizz should also return true
 */
public function bar(Foo $foo, Bar $bar)
{
    yield assert($foo->bar());
    yield assert($bar->fizz());
}
```

> Testing multiple related objects is referred to as an "integration test". See
> the corresponding section in this manual for more information.

Note that it is _not_ possible to inject an object of the same type twice; this
causes a segmentation fault. In rare cases where this is needed ("pyramid
structure") you can construct either of them manually using the `::resolve`
static method.

## Passing parameters
If a parameter is a type-hinted object, Gentry will instantiate that object for
you. This is true of both the test method as well as anonymous functions
specifying a method to test on an injected object. Any parameter with a default
value will get that value assigned, and all parameters can be passed by
reference allowing just-in-time modification.

## Testing static methods and properties
To test a static method, simply call it statically on the injected object. If
the class in question is `abstract`, don't worry: Gentry wraps it in an
anonymous class for you:

```php
<?php

class MyFirstTest
{
    /**
     * 'bar' is called statically
     */
    public function statically(Foo $foo)
    {
        yield assert($foo::bar());
    }
}
```

To test a static property, you would simply have written something like the
following:

```php
<?php

// ...
        yield assert(Foo::$bar);
// ...
```

## Testing if a certain exception is thrown
Catch the expected exception in your test and `assert` its type. This creates
two code paths (the `catch` and the normal execution. Make sure you also add an
assertion for when the exception isn't thrown (which should presumably cause the
test to fail):

```php
<?php

class MyTest
{
    /**
     * Assuming it would throw an exception...
     */
    public function itShouldThrowAnException(Foo $foo)
    {
        $e = null;
        try {
            $foo->bar();
        } catch (Exception $e) {
        }
        yield assert($e instanceof MyExpectedException);
    }
}
```

## Testing if something has output
Gentry buffers all output. If any tested method emits output, it by default
assumes this is OK but issues a warning. To test something's output, buffer it
in your test and `assert` it as for all tests:

```php
<?php

class MyTest
{
    /**
     * It has the correct output
     */
    public function checkOutput(Foo $foo)
    {
        ob_start();
        $foo->helloWorld();
        yield assert('Hello world!' == ob_get_clean());
    }
}
```

This test will fail if `Foo::helloWorld()` produces a different output.

Note that the output buffer is reset after each breakpoint, so to also `assert`
the method's return value you would need an intermediate variable:

```php
<?php

class MyTest
{
    /**
     * It has the correct output
     */
    public function checkOutput(Foo $foo)
    {
        ob_start();
        $return = $foo->helloWorld();
        yield assert('Hello world!' == ob_get_clean());
        yield assert($return);
    }
}
```

## Marking incomplete tests
Test methods annotated with `@Incomplete` are skipped and will only issue a
warning. This allows you to quickly skip methods you're still working on, but
would otherwise cause the tests to fail (and e.g. a Git pre-push hook to cause
abortion of a commit).

