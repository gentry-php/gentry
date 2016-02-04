# Writing tests for Gentry

## The basics
Each testable feature should be placed in your `"tests"` directory or one of its
subdirectories (Gentry recurses for you) You can (optionally) describe the
feature by adding a doclbock to the class:

```php
<?php

/**
 * This is going to explain how this should work
 */
class MyFirstTest
{
}
```

The description is just for clarity, it doesn't itself do anything.

Each test class should completely test a certain "feature" of your application.
Exactly what "a feature" entails is in part a matter of taste, but an example
could be `"A user can place an order in the webshop."` The test would then cover
ordering a product, applying discounts, ordering multiple products in one
session, paying for the order, handling declined payments etc. etc. etc.

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

Add a _public_ method to your test class. It can have any name, but its
parameters *must* be the type-hinted thing(s) we are going to test. Gentry
assumes all a test class's public methods run tests, and you only need to test
public methods on your classes.

> Obviously, to write helper methods, simply declare them `protected` or
> `private`.

```php
<?php

/**
 * This is going to explain how this should work
 */
class MyFirstTest
{
    /**
     * {0} should return true
     */
    public function bar(Foo $foo)
    {
        yield 'bar' => function () {
            yield true;
        };
    }
}
```

The first thing to note is that the test `yield`s the expected value and is thus
a "generator". This allows you to specify multiple tests for one scenario.

> Gentry test methods must _always_ return a generator. If the tested method has
> no return value, just `yield null` instead.

Under the key `bar` (the method to test) we yield an anonymous function. This
tells Gentry we're going to call a method on `Foo`. If the method under test
requires arguments, specify them on the callable:

```php

// ...
yield 'bar' => function ($foo = 1, $bar = 2) {
    yield true;
};
```

> If arguments need "more work" to setup (e.g. objects constructed with
> parameters) assign a default of `null`, pass as a reference and inject the
> desired value before `yield`ing.

The value(s) `yield`ed by the callable is what the tested method is expected to
return. If you `yield` multiple times, Gentry will test the method multiple
times too. This is useful for asserting that for multiple calls the same result
is always returned (or of course not, if that's what your method does). If you
passed the parameters by reference, you can also trigger multiple invocations
with _different_ arguments.

## Testing properties
Gentry can test for both method calls and properties. To test a property, don't
yield a callable but just the expected value. Assuming we change the `Foo` class
to have a public property `$baz = true`:

```php
<?php

/**
 * This is going to explain how this should work
 */
class MyFirstTest
{
    /**
     * {0} should contain true
     */
    public function bar(Foo $foo)
    {
        yield 'baz' => true;
    }
}
```

## Testing multiple related objects
You can use multiple `"{n}"` annotations in your scenario. They will be tested
in order, and `"n"` is the argument number to test on.

The `yield`ed value(s) of the test method are simply what calling the designated
method or whatever check on a property would be expected. _Is it that simple?_
Yes, it's that simple to write a test in Gentry :)

Note that the `n` in `"{n}"` refers to the argument number, not the order of the
`yield`. So you can use `"{0}"` as often as you want, but if the first mentioned
annotation is `"{1}"` that will apply to the first `yield` statement:

```php
<?php

// ...
/**
 * {1} should be true, and {0} should also return true
 */
public function bar(Foo $foo, Bar $bar)
{
    // Bar::$fizz
    yield 'fizz' => true;
    // Foo->bar()
    yield 'bar' => function () {
        yield true;
    };
}
```

> Testing multiple related objects is referred to as an "integration test". See
> the corresponding section in this manual for more information.

## Assertion logic
Gentry uses some simple assertion logic to compare return values:

- If both the expected and actual result are numeric, the test passes if simple
  equality (`==`) returns true;
- If both the expected and actual result are objects, the test passes if simple
  equality (`==`) returns true;
- Otherwise, the test passes if strict equality (`===`) returns true.

In practice, this means that `1.0` and `1` are considered a match, as well as
objects with the same class _and_ the same properties. If for some reason you
need to test objects for strict equality, yield a custom function (see the
section on complex tests for more information).

## Passing parameters
If a parameter is a type-hinted object, Gentry will instantiate that object for
you. This is true of both the test method as well as anonymous functions
specifying a method to test on an injected object. Any parameter with a default
value will get that value assigned, and all parameters can be passed by
reference allowing just-in-time modification.

## Testing static methods and properties
To test a static method, simply give the object under test a default value of
`null`, *but* don't assign it anything:

```php
<?php

class MyFirstTest
{
    /**
     * {0} is called statically
     */
    public function statically(Foo $foo = null)
    {
        yield 'bar' => function () {
            yield true;
        };
    }
}
```

Unsurprisingly, testing a static property works the same except you `yield` the
expected value, not another generator,

If you need to do setup on static classes under test, just do so in the test
method itself:

```php
<?php

class MyFirstTest
{
    /**
     * {0} equals true
     */
    public function statically(Foo $foo = null)
    {
        Foo::$bar = true;
        yield 'bar' => true;
    }
}
```

## Testing if a certain exception is thrown
Simply yield an instance of that same exception from your test method!

> Don't actually `throw` the exception - that would exit the generator so you
> wouldn't be able to test anything subsequent (like non-exception for other
> parameter values). Obviously the feature-under-test _is_ expected to `throw`
> it when applicable.

```php
<?php

class MyTest
{
    /**
     * Assuming {0} would throw an exception...
     */
    public function itShouldThrowAnException(Foo $foo)
    {
        yield 'bar' => function () {
            yield new Exception;
        };
    }
}
```

This test passes if the exception is of the same class as the exception the
actual method call throws.

Since the test simply `yield`s the exception, we can test multiple exceptions,
return values and whatnot in one test:

```php
<?php

class MyTest
{
    /**
     * Assuming {0} would throw an exception when called with `false`,
     * but {0} succeeds when called with `true`...
     */
    public function itShouldThrowAnException(Foo $foo)
    {
        yield 'bar' => function ($param = false) {
            yield new Exception;
        };
        yield 'bar' => function ($param = true) {
            yield true;
        };
    }
}
```

## Testing if something has output
Echo the expected output in your test method:

```php
<?php

class MyTest
{
    /**
     * {0} has the correct output
     */
    public function checkOutput(Foo $foo)
    {
        yield 'helloWorld' => function () {
            echo 'Hello world!';
            // Note: assuming `helloWorld` has no return value, we need to
            // expect `null`. In PHP, functions not returning anything actually
            // return `null`.
            yield null;
        };
    }
}
```

This test will fail if `Foo::helloWorld()` produces a different output.

Gentry will trim the output for convenience, and will also remove any console
formatting. Note that the output buffer is reset for each run. E.g. this
fictional test would succeed:

```php
<?php

class MyTest
{
    /**
     * {0} has the correct output, as does {0}
     */
    public function checkOutput(Foo $foo)
    {
        yield 'helloWorld' => function () {
            echo 'Hello world!';
            yield null;
        };
        yield 'helloMars' => function () {
            echo 'Hello Mars!';
            yield null;
        };
    }
}
```

## Marking incomplete tests
Test methods annotated with `@Incomplete` are skipped and will only issue a
warning. This allows you to quickly skip methods you're still working on, but
would otherwise cause the tests to fail (and e.g. a Git pre-push hook to cause
abortion of a commit).

