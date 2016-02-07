# Testing command line scripts
More complex projects usually also contain one or more command line scripts.
Wouldn't it be awesome if we can test those too? _Guess what:_ we can!

## When you don't need this
In certain frameworks (e.g. Laravel), commands are defined as objects extending
a command class or such. If you're using one of those, you can probably simply
inject the command class and test it normally (maybe defining some stuff like
CLI parameters are arguments).

If you're like us though, shell scripts will usually just be a procedural
collection of PHP statements. They'll often use classes from elsewhere in your
code base - like models - but the idea is that we want to test the script's
execution in its entirety.

## Adding a command line test
Assuming the script you want to test is `bin/myscript`, add a test method like
so:

```php
<?php

class CliTest
{
    /**
     * Running myscript should exit with 0.
     */
    public function()
    {
        passthru('/path/to/myscript', $return);
        yield assert($return == 0);
    }
}
```

> In the Unix world, it's customary to exit with 0 if a script runs
> successfully and with an error code on failure. Of course, your own scripts
> are free to violate this decade-old convention - you can `yield 'whatever'`.

The forwarded call automatically has its `GENTRY` environment variable set, so
assuming your script uses the same logic for determining e.g. which database to
use as the rest of your code you should be good to go.

If you use a different logic and/or need other environment variables, feel free
to define them in the command passed to `passthru`.

## Setup and teardown
This works of course in the exact same way as for any other test class in
Gentry. `__construct`/`__destroy` for the feature as a whole, and
`__wakeup`/`__sleep` on a per-scenario basis.

## Testing script output
If your script emits output you need to test, just test it as if you were
testing normal output: `echo` the expected output in the test method and watch
the test succeed (or fail, of course).

## Asserting application state
It's cool that we can test the exit code of a script this way, but often you'll
also want to test other aspects of your application before and/or after the
script runs. E.g. if our script cleans up unprocessed orders older than a day
(on the assumption that nobody's going to pay for them anymore...), we might
like to assert that prior to the script running there's at least one such order,
and that it's gone afterwards - since running the script shouldn't exit with an
error if nothing was to be deleted, that could be a perfectly fine situation.

Well, of course we can use a CLI test inside an integration test! Here's some
pseudo-code illustrating that:

```php
<?php

class CliIntegrationTest
{
    /**
     * There is one old order {?}. After running the script {?}, there should be
     * zero {?). Finally, running the script again doesn't do anything but also
     * doesn't give an error.
     */
    public function oldOrders(OrderService $orders)
    {
        // Assuming our fixture contains one such order for testing...
        yield assert(count($orders->getOldOrders('-1 day')) == 1);
        passthru('bin/cleanup', $result);
        yield assert($result == 0);
        yield assert(count($orders->getOldOrders('-1 day')) == 0);
        yield assert($result == 0);
    }
}
```

First, we assert that our test fixture indeed has an order older than a day (the
workings of `OrderService::getOldOrders` aren't important, you can guess what
its code would look like). It's important that the test fails if not, since
otherwise we could never assert that the script really is working :)

Second, we run our script and assert it finishes with no errors.

Thirdly, we assert that after the script has run, `getOldOrders` no longer
returns any items.

Finally, we assert that running the script when it has nothing do also works
without any errors.

## Grouping logic for CLI tests
There's two schools of thought here, each with its own merits. Use whatever
appeals most to you.

### 1. Group all CLI tests in one feature test
This is the `TestCLI` class approach, related to the "group all test for methods
of class `A` into an `ATest` feature class".

### 2. Bundle CLI tests with the feature they belong with
This is our personal approach, but YMMV. For a feature describing and testing
the order process, we would like the script nullifying old orders to be tested
there. But seriously, it's all a matter of preference.

