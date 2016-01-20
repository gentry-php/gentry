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
A regular expression of classnames to ignore. Useful for automatically ignoring
classtypes that are hard to test, e.g. controllers.

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
Each scenario should be placed in your `"tests"` directory or one of its
subdirectories (Gentry recurses for you) You can (optionally) describe the
scenario by annotating the class with an `@Description` docblock:

```php
<?php

/**
 * @Description This is going to explain how this should work.
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

Add a _public_ method to your test class of the same name as the method you want
to test, with the type-hinted object as the first parameter. Gentry assumes all
a test class's public methods run tests, and you only need to test public
methods on your classes.

> Obviously, to write helper methods, simply declare them `protected` or
> `private`.

```php
<?php

/**
 * @Description This is going to explain how this should work.
 */
class MyFirstTest
{
    /**
     * @Description This will test the bar method
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

The return value of the test method is simply what calling the designated method
would be expected to return. _Is it that simple?_ Yes, it's that simple to write
a test in Gentry :)

## Setup/teardown
Setup (like injecting mocks) should be done in the _constructor_ of your
testclass. Likewise, teardown in the `__destroy` magic method.

For per-test setup and teardown (like loading a database fixture), use
`__wakeup` and `__sleep` respectively.

## Using a base class
If multiple tests share the same `__sleep` and `__wakeup` methods for instance,
you'll want to use a base class or a trait for that. Any class declared as
`abstract` is ignored by Gentry when checking for tests.

## Using test objects
Often you'll want a set of classes to use as mocks in your test. Just place them
in a different directory than your tests, e.g. define `tests/specs` as the
`"tests"` directory and place your mocks in `tests/mocks` and have your
autoloader handle them.

## Injecting parameters containing a non-simple value
If your method under test should be called with a non-simple value (usually an
object), the parameter name _will_ matter.

Assume our `Foo` class looks as follows:

```php
<?php

class Foo
{
    function bar(Bar $one, Baz $two)
    {
        return $one->fizz && $two->buzz;
    }
}
```

The corresponding test could look like this:

```php
<?php

class MyTest
{
    public function __construct()
    {
        $this->one = new Bar;
        $this->two = new Baz;
    }

    /**
     * @Description Assuming $one->fizz is true but $two->buzz is false
     */
    public function bar(Foo $foo, Bar $one, Baz $two)
    {
        return false;
    }
}
```

> Obviously the `fizz` and `buzz` properties could be either `true` or not
> depending on conditions, presumably during construction or what they get from
> the database.

The order of resolution for arguments is as follows:

1. If a default value is given, Gentry uses that;
2. If a public property of the same name exists on the test class, Gentry uses
   that;
3. If the argument is type hinted, Gentry constructs a new instance of that type
   for you;
4. If all these fail, Gentry will pass `null`.

> So in the above example, we could have used _either_ type hinting _or_ setting
> instances on the test class.

If a known variable (of any name) is placed on your class's public scope, you
may also simply pass it _by reference_ and update its value before returning:

```php
<?php

class MyTest
{
    public $baz;

    public function bar(Foo $foo, &$baz)
    {
        $baz = 1;
        // Foo::bar should return 2 when called with 1
        return 2;
    }
}
```

## Testing a method in multiple scenarios
Often you'll need to test a method with multiple calls passing different values.
Use the `@Method methodName` annotation on your test to hardcode the name of the
method under test, and simply give your test method a descriptive, unique name.

## Testing if a certain exception is thrown
Simply throw that same exception from your test method!

```php
<?php

class MyTest extends Scenario
{
    /**
     * Description Assuming Foo::bar would throw an exception...
     */
    public function bar(Foo $foo)
    {
        throw new Exception;
    }
}
```

This test passes if the exception thrown by the test is of the same class as the
exception the actual method call throws.

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
     * @Description It should contain 3 items
     * @Pipe count
     */
    public function threeItems(Foo $foo, $bar)
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

## Marking incomplete tests
Tests annotated with `@Incomplete` are skipped and will only issue a warning.

## Generating missing tests
Run Gentry with the `-g` flag to generate skeletons for missing tests for you.
Generated tests will be placed in the directory specified by `tests` under a
guesstimated name, and marked as `@Incomplete` by default.

Note that you'll probably want to re-group generated tests into classes that
make sense for your application.

