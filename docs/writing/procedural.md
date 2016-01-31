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
with default values.

## Integration testing with procedural functions
Note that this is typically a sign of bad application design
