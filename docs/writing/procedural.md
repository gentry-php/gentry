# Testing procedural code
Sometimes your code contains "procedural code" you also need to test. Procedural
code (for want of a better term) is just code that isn't inside a class. Think
of regular functions and `include`d files.

## Functions
Testing functions is much like a regular unit test, only we inject a `callable`
instead of an object argument to work on.

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
    public function test(callable &$function = null)
    {
        $function = 'myAwesomeFunction';
        yield function () {
            yield true;
        };
    }
}
```

Gentry will detect that the argument is type-hinted as `callable` and will
create a `Procedure` test.

Two other things of note: we _must_ pass a reference in this case and assign it
the name of the function to test inside our test method, since Gentry has
otherwise no way of knowing what your function is to be called. The alternative
would have been to omit the `callable` typehint, but this is more declarative
(other programmers looking at your test will instantly see from its definition
that a function is to be tested).

Yield an anonymous function specifying the arguments to call the function under
test with as you normally would. Note that we do not specify a `yield`ed key
since this would not make sense for "normal" functions.

The keys/values `yield`ed by the function can be pipes as normally.

> Gentry doesn't auto-include the file with your function declaration, so you
> must do that in your test. Gentry doesn't auto-include anything (it might
> screw up the order of things in your application), so if it can't be loaded
> via PHP's autoload mechanism, you'll have to it manually.

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
desired values.

> Tip: You'll probably want to add such a test _alongside_ the "normal" test for
> the function. This stops Gentry from complaining about "missing tests" but
> more importantly allows you to test the function proper before testing the
> integration aspect, which usually makes sense.

Did we mention global procedural functions altering application state are a
_Really Bad Idea_(tm)?

## Regular code
Okay, seriously, this kind of stuff is _really_ bad form. Gentry won't even try
to generate skeletons for you - you really should only use procedural code in
bootstrap-type files, e.g. to setup routing or register dependencies. And of
course an `index.php` entry point for your application.

Having said that, we're aware that legacy code floats around so we want to at
least try and help you to test that too. Let's see what we can do:

```php
<?php

class ProceduralCodeTest
{
    /**
     * {0} includes a file that returns 1
     */
    public function(SplFileInfo &$file = null)
    {
        $file = new SplFileInfo('path/to/file.php');
        yield 1;
    }
}
```

Type-hint an argument with the `SplFileInfo` class. This tells Gentry we're
going to test procedural code for that argument.

Regular procedural code almost by default needs to be only integration tested,
so instead of polluting global namespace Gentry places each variable assigned in
a test procedural file onto the `SplFileInfo` object that was injected. So if we
need to test that inside `path/to/file.php` a variable `$foo` is set to a
`new Bar`, simply do this:

```php
<?php

class ProceduralCodeTest
{
    /**
     * {0}::$foo is set to a Bar.
     */
    public function(SplFileInfo &$file = null)
    {
        $file = new SplFileInfo('path/to/file.php');
        yield new Bar;
    }
}
```

