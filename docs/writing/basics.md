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

Add a _public_ method to your test class. It can have any name, but the first
parameter *must* be the type-hinted object we are going to test. Gentry assumes
all a test class's public methods run tests, and you only need to test public
methods on your classes.

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
     * {0}::bar should return true
     */
    public function bar(Foo $foo)
    {
        return true;
    }
}
```

Gentry can test for both method calls and properties. Method calls are only
allowed on the first passed parameter to a test (`"{0}"` or `$foo` in this
example). The first argument should _always_ be a type hinted object.

Subsequent arguments are variables that should be injected into the method under
test, in the correct order. The default values are the values we are going to
test the method with in this particular scenario.

You can use multiple `"{n}"` annotations in your scenario. They will be tested
in order, and `"n"` is the argument number to test on.

The return value of the test method is simply what calling the designated method
would be expected to return. _Is it that simple?_ Yes, it's that simple to write
a test in Gentry :)

## Testing properties
You can also test _properties_ of the object by annotating with `{n}::$property`
instead:

```php
<?php

class Foo
{
    public $baz = false;
}
```

In this case, the test method should return the expected value of the property:

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

## Testing static methods and properties
To test a static method, simply give the object under test a default value of
`null`, *but* don't assign it anything:

```php
<?php

class MyFirstTest
{
    /**
     * @Scenario {0}::bar is called statically
     */
    public function statically(Foo $foo = null)
    {
        return true;
    }
}
```

Unsurprisingly, testing a static property works the same, only use `{0}::$bar`
in your scenario annotation.

If you need to do setup on static classes under test, just do so in the test
method itself:

```php
<?php

class MyFirstTest
{
    /**
     * @Scenario {0}::$bar equals true
     */
    public function statically(Foo $foo = null)
    {
        Foo::$bar = true;
        return true;
    }
}
```

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

Gentry will trim the output for convenience, and will also remove any console
formatting.

## Marking incomplete tests
Tests annotated with `@Incomplete` are skipped and will only issue a warning.

