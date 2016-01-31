# Testing procedural functions
Sometimes your code contains "procedural functions" you also need to test.
Procedural functions (for want of a better term) are just plain functions that
aren't class methods. Testing these is like a regular unit test, only without an
injected object argument.

## Setting up the test
Pass the function name as the first argument and type-hint is as `callable`:

```php
<?php

function myAwesomeFunction()
{
    return true;
}

class ProcedureTest
{
    /**
     * {0} should return true
     */
    public function test(callable $function = 'myAwesomeFunction')
    {
        yield true;
    }
}
```

Gentry will detect that the string argument statisfied `is_callable` and will
create a `Procedure` test instead of an `Executable`.

If the function expects arguments, simply pass them as additional parameters
with default values as you would normally do.

## Integration testing with procedural functions
This reeks of bad application design, so it isn't offically supported. But if
you really need to test the effects of a procedural function on your general
application you can patch a custom test together, e.g.:

```php
<?php

class ProcedureIntegrationTest
{
    /**
     * {0}::$one should be true. After, {0}::$two should also be true.
     */
    public function test(stdClass &test)
    {
        $test->one = myAwesomeFunction();
        yield function ($result) {
            return myAwesomeFunction();
        };
        $test->two = getNewApplicationState();
        yield true;
    }
}
```

In short, you inject a dummy object and populate and test properties with the
desired values. The downside is that Gentry will now keep complaining about a
missing test for that function. You could mark is as `@Untestable` to suppress
the warning; these kinds of methods are pretty untestable anyway, like using
global variables. Or just ignore the warning. C'est la vie.

