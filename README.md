# Gentry
A unit testing framework for PHP5.5+

Good programmers are lazy, but unfortunately that means that stuff like writing
unit tests (boooooring) is often skipped. Please don't; it's important and oh
so handy once you have them in place.

Gentry was designed with three goals in mind:

1. To make writing unit tests _so_ easy even the worst slacker will bother;
2. To alleviate writing boilerplate code by generating skeletons for you.
3. Speed. You want to run tests before you push, so if they're slow that's
   _annoying_.

## Installation

### Composer (recommended)
```sh
composer require --dev monomelodies/gentry
```

You can now run `vendor/bin/gentry`.

### Manual
Download or clone the repo. There's an executable in the root.

## Configuration
Create a `Gentry.json` file in the root of your project. It uses the following
options:

```json
{
    "src": "/path/to/src",
    "tests": "/path/to/tests",
    "includePath": "/my/include/path",
    "bootstrap": "/path/to/bootstrap.php",
    "namespace": "Foo",
    "ignore": "some.*?regex"
}
```

### string `src` ###
### string `tests` ###
Gentry makes two assumptions:

1. Your source files are in a directory (`"/path/to/src"`).
2. Your tests are in another directory (`"path/to/tests"`).

If these two are mixed, clean that up first. Seriously.

Both `src` and `tests` can be either absolute, or relative to the root - hence
`"/path/to/root/src"` could be simplified to just `"src"`.

### string|array `includePath` ###
The `"includePath"` option specifies optional `set_include_path` values to set
before attempting to test anything. If omitted or empty, the defaults are used.

You can pass either a single string or an array of values. Note that these
values are passed verbatim to PHP's `set_include_path` function and should thus
be resolvable from the current path.

### string|array `bootstrap` ###
The path(s) to file(s) ("bootstrapper(s)") every piece of code in your
application needs. This is usually something that would reside in an `index.php`
entry point or similar file. These files are otherwise ignored by Gentry when
analysing your code and should do stuff like initialise an autoloader.

You can also pass an array of files instead of a string. They will be prepended
in order.

`includePath` is parsed before `bootstrap`, so if you use them in conjunction
you could use relative paths here. Otherwise, they will be relative to
`get_cwd()`.

### string `namespace` ###
Namespace to use for generated tests. Useful if you specifically add that
namespace to `autoload-dev` in your `composer.json`.

### string `ignore` ###
A regular expression of classnames to ignore in the `"src"` path. Useful for
automatically ignoring classtypes that are hard to test, e.g. controllers. You
could also utilise this if your tests and sourcecode are mixed (but seriously,
don't do that).

## Usage
Now run Gentry from the command line and see what happens:

```sh
vendor/bin/gentry
```

It'll complain that it can't do anything yet. Which makes sense, we haven't
written any tests yet!

## Verbose mode
If you'd like more info, run Gentry with the `-v` flag:

```sh
vendor/bin/gentry -v
```

In the default mode, only important messages are displayed. But verbose mode
might be handy when something's going wrong for you, or if you simply want
feedback about stuff like incomplete tests.

## Writing tests
Each testable feature should be placed in your `"tests"` directory or one of its
subdirectories (Gentry recurses for you) You can (optionally) describe the
feature by annotating the class with an `@Feature` docblock:

```php
<?php

/**
 * @Feature This is going to explain how this should work
 */
class MyFirstTest
{
}
```

The description is just for clarity, it doesn't itself do anything.

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

Add a _public_ method to your test class. It can have any name, but the first
parameter *must* be the type-hinted object we are going to test. Gentry assumes
all a test class's public methods run tests, and you only need to test public
methods on your classes.

> Obviously, to write helper methods, simply declare them `protected` or
> `private`.

```php
<?php

/**
 * @Feature This is going to explain how this should work
 */
class MyFirstTest
{
    /**
     * @Scenario {0}::bar should return true
     */
    public function bar(Foo $foo)
    {
        return true;
    }
}
```

The first argument is the type-hinted object you want to check. Subsequent
arguments are variables that should be injected into the method under test, in
the correct order. The default values are the values we are going to test the
method with in this particular story.

Note the `@Scenario` annotation. This should describe what we are going to test
exactly (just human readable). The special `{0}::bar` syntax tells Gentry
exactly what we are testing programmatically. `{0}` is simply the first argument
to the method, and `::bar` says we want to test the `bar` method on that object.

You can also test _properties_ of the object by annotating with `{0}::$property`
instead:

```php
<?php

class Foo
{
    public $baz = false;
}
```

```php
<?php

class MyFirstTest
{
    /**
     * @Scenario {0}::$baz should be false
     */
    public function baz(Foo $foo)
    {
        return false;
    }
}
```

The return value of the test method is simply what calling the designated method
would be expected to return. _Is it that simple?_ Yes, it's that simple to write
a test in Gentry :)

## Assertion logic
Gentry uses some simple assertion logic to compare return values:

- If both the expected and actual result are numeric, the test passes if simple
  equality (`==`) returns true;
- If both the expected and actual result are objects, the test passes if simple
  equality (`==`) returns true;
- Otherwise, the test passes if strict equality (`===`) returns true.

In practice, this means that `1.0` and `1` are considered a match, as well as
objects with the same class _and_ the same properties.

## Passing parameters
If your method under test needs arguments, pass them as default parameters to
your test method:

```php
<?php

class Foo
{
    public function bar($param1, $param2)
    {
        return $param1 && $param2;
    }
}
```

```php
<?php

class MyFirstTest
{
    /**
     * @Scenario {0}::bar should return true if both parameters are true
     */
    public function bothTrue(Foo $foo, $param1 = true, $param2 = true)
    {
        return true;
    }

    /**
     * @Scenario {0}::bar should return false if either parameter is not true
     */
    public function firstFalse(Foo $foo, $param1 = false, $param2 = true)
    {
        return false;
    }

    /**
     * @Scenario {0}::bar should return false if either parameter is not true
     */
    public function secondFalse(Foo $foo, $param1 = true, $param2 = false)
    {
        return false;
    }
}
```

If a parameter is a type-hinted object, Gentry will instantiate that object for
you. This is true of both the object-under-test as well as any other parameters.

## Injecting parameters containing a non-simple value
If an injected parameter needs a bit more work (e.g. an object with construction
parameters) give it a default value of `null`, pass it as a reference and
construct it in your test method:

```php
<?php

class MyFirstTest
{
    /**
     * @Scenario {0}::bar called with complex object returns true.
     */
    public function complex(Foo $foo, Bar &$bar = null)
    {
        $bar = new Bar(1, 2, 3);
        return true;
    }
}
```

Again, this is true for both the object-under-test as well as any subsequent
parameters.

## Setup/teardown
Setup (like injecting mocks) should be done in the _constructor_ of your
testclass. Likewise, teardown in the `__destroy` magic method.

For per-test setup and teardown (like loading a database fixture), use
`__wakeup` and `__sleep` respectively.

Each of these methods may be annotated with an `@Description`. Note this will
only be outputted when running in verbose mode.

## Using a base class
If multiple tests share the same `__sleep` and `__wakeup` methods for instance,
you'll want to use a base class or a trait for that. Any class declared as
`abstract` is ignored by Gentry when checking for tests.

## Using test objects
Often you'll want a set of classes to use as mocks in your test. Just place them
in a different directory than your tests, e.g. define `tests/specs` as the
`"tests"` directory and place your mocks in `tests/mocks` and have your
autoloader handle them.

## Testing if a certain exception is thrown
Simply throw that same exception from your test method!

```php
<?php

class MyTest
{
    /**
     * Scenario Assuming {0}::bar would throw an exception...
     */
    public function itShouldThrowAnException(Foo $foo)
    {
        throw new Exception;
    }
}
```

This test passes if the exception thrown by the test is of the same class as the
exception the actual method call throws.

## Testing if something has output
Echo the expected output in your test method:

```php
<?php

class MyTest
{
    /**
     * Scenario {0}::helloWorld has the correct output
     */
    public function checkOutput(Foo $foo)
    {
        echo 'Hello world!';
    }
}
```

This test will fail if `Foo::helloWorld()` produces a different output.

### Raw output
By default, Gentry will compare the `trim`med output of both the test as well as
the method under test. To suppress this behaviour (e.g. if a method outputs an
important number of whitespace, like `str_pad`) annotate the test with `@Raw`.

## Piping return values
Often you'll want to test something more complex than a simple scalar value.
E.g. whether or not a returned array has a `count` of 3. There are two ways to
"pipe" the result through your test when checking:

### The `@Pipe` annotation
For simple checks, you can annotate the test method with `@Pipe`. Its argument
should be a single callable method to run the result through:

```php
<?php

class MyArrayTest extends Scenario
{
    /**
     * @Scenario count({0}::$bar) should equal three.
     * @Pipe count
     */
    public function threeItems(Foo $foo)
    {
        return 3;
    }
}
```

### Returning a callable
For more complicated checks, your test method can return a callable. This will
first be invoked with the result value of the tested method and should return
`true` if the test passed, or otherwise `false`:

```php
<?php

class MyMoreComplicatedArrayTest extends Scenario
{
    /**
     * @Description It should contain three items, each of which is a
     *  `StdClass`.
     */
    public function threeStdClassObjects(Foo $foo, $bar)
    {
        return function ($result) {
            if (count($result) != 3) {
                return false;
            }
            foreach ($result as $item) {
                if (!($item instanceof StdClass)) {
                    return false;
                }
            }
            return true;
        };
    }
}
```

When a callable is returned from a test (or, technically, an instance of
`Closure`), the expected return value is _always_ casted to true. The actual
check - as opposed to simple comparison - should be done in your callable.

> If a callable is actually what's expected of the method under test, return a
> callable that returns `$result instanceof Closure`.

## Grouping tests
To run a group of related tests, return an instance of `Gentry\Group` instead.
The grouped tests work on the object injected in the test method, but can
specify their own parameters, annotations and return checks. An example:

```php
<?php

use Gentry\Group;

class GroupedTesting
{
    /**
     * @Scenario An optional scenario for the grouped tests. Note we don't need to use the {0}::method syntax now.
     */
    public function testAGroup(Foo $foo)
    {
        return new Group($this, $foo, [
            /**
             * @Scenario {0}::foo should return true.
             */
            function () { return true; },
            /**
             * @Scenario {0}::bar should return false.
             */
            function () { return false; },
        ]);
    }
}
```

Note that there is no need to pass `$foo` to each callable. Otherwise, all
"regular" rules apply as for single tests.

> A group of tests only calls the optional `__wakeup` and `__sleep` before and
> after the _entire_ group is run. So if you need to run subsequent tests
> against e.g. a database grouped tests are the way to go.

## Repeating tests
Sometimes you want the exact same test to be repeated a number of times, e.g. to
assure it returns the same result for each consecutive run. Annotate the test
with `@Repeat [number]` to accomplish this.

> Like with grouped tests, `__wakeup` and `__sleep` are only called once for
> all iterations. If you specifically need to retest a method including setup
> and teardown, declare it as non-public and add some facade methods that
> forward their calls.

Note that repeated tests producing output will need to match the entire output
for all iterations, concatenated.

## Marking incomplete tests
Tests annotated with `@Incomplete` are skipped and will only issue a warning.

## Generating missing tests
Run Gentry with the `-g` flag to generate skeletons for missing tests for you.
Generated tests will be placed in the directory specified by `tests` under a
guesstimated name, and marked as `@Incomplete` by default.

Note that you'll probably want to re-group generated tests into classes that
make sense for your application.

