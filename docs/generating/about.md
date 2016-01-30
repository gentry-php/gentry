# Generating tests
Run Gentry with the `-g` flag to generate skeletons for missing tests for you.
Generated tests will be placed in the directory specified by `tests` under a
random name (much like Composer's generated autoloader) and are marked as
`@Incomplete` by default.

Note that you'll probably want to re-group generated tests into classes that
make sense for your application.

## Structuring tests
While test classes can contain any combination of scenarios, it would make sense
obviously to regroup the generated tests into multiple classes containing all
test methods related to a "feature".

The simple way would be to just place all tests for a singular class together,
but in practice it might make a lot more sense to group your tests in a way that
would make sense to users of your application. E.g., all tests pertaining to
account sign up could be in a `SignupTest` class, and cover methods on a `User`
model, various controllers, confirmation email sending etc.

## The generated tests
Gentry does its best to setup your tests with something useful. I.e., it tries
hard to pass usable default values and check return types based on analysing the
source code. Of course, it's not clairvoyant so you'll probably want to adapt a
number of things to get your tests to work properly.

The generated checks, if possible, yield `is_typename' => true;`. Of course,
you'll probably want to check different things.

## What does Gentry generate skeletons for?
The generator goes through your entire source code as specified in `Gentry.json`
and analyses every file. Only classes and traits are considered testable in the
current version.

Methods are considered testable if they are public and are defined on the class
under test, _or_ on a parent class _but_ that parent class is abstract. To
prevent code duplication, you can mark base methods as `@Untestable` but
implement a single test on one of the extending classes anyway (`@Untestable` is
only considered during test generation, not actual test execution.

To test trait methods, one should pass a referenced existing class (we recommend
simply `stdClass`) with a default `null` and set it to an anonymous class inside
your test:

```php
<?php

trait myTrait
{
    public function something()
    {
        return true;
    }
}

class Test
{
    /** {0}::something returns true */
    public function something(stdClass $test = null)
    {
        $test = new class() extends stdClass {
            use MyTrait;
        };
        yield true;
    }
}
```

Anonymous classes are a PHP7 feature. To achieve the same in PHP5, you can use
`eval` (one of the few valid uses for it...):

```php
<?php

class Test
{
    /** {0}::something returns true */
    public function something(stdClass $test = null)
    {
        if (!class_exists('a_dummy_name')) {
            eval("class a_dummy_name extends stdClass {
                use MyTrait;
            }");
        };
        $test = new $test;
        yield true;
    }
}
```

Gentry correctly generates this skeleton for you.

