# Testing procedural code
Sometimes your code contains "procedural code" you also need to test. Procedural
code (for want of a better term) is just code that isn't inside a class. Think
of regular functions and `include`d files.

## Functions
Testing functions is much like a regular test, only we inject a `callable`
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
     * myAwesomeFunction should return true
     */
    public function test(callable &$function)
    {
        yield assert($function('myAwesomeFunction'));
    }
}
```

The injected callable is a wrapper for a user-defined function (much like an
injected object gets wrapped). The first argument is the name of the function
we'd like to test, and all subsequent arguments are whatever the function
originally expects.

> Gentry doesn't auto-include the file with your function declaration, so you
> must do that in your test. Gentry doesn't auto-include anything (it might
> screw up the order of things in your application), so if it can't be loaded
> via PHP's autoload mechanism, you'll have to it manually.

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
     * We include a file that returns 1
     */
    public function()
    {
        yield assert(1 == include 'path/to/file.php');
    }
}
```

If the included file produces output, echo what you expect before the yield as
you normally would.

Note that since the include is _inside_ the test method, it won't pollute the
global namespace unless your included file specifically tampers with global
variables. But of course that would be a Really Bad Idea(tm).

You can use `__wakeup` and/or `__sleep` to reset globals if it's really
something you need to test.

> Caution: don't do something like `$GLOBALS = []`, it leads to unpredictable
> results since it also deletes Gentry-related globals.

