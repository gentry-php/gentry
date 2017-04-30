# Gentry
Test generation tools for PHP7+.

Good programmers are lazy, but unfortunately that means that stuff like writing
tests (boooooring) is often skipped. Please don't; it's important and oh so
handy once you have them in place.

Gentry was designed with three goals in mind:

1. To make writing tests _so_ easy even the worst slacker will bother;
2. To alleviate writing boilerplate code by generating skeletons for you.
3. Speed. You want to run tests before you push, so if they're slow that's
   _annoying_.

## Prerequisites
- Make sure you have PHP7. Seriously, Gentry uses some new features so it'll
  fail miserably on anything older.
- Turn on assertions and configure them to throw `AssertionError` on failure.
  See [this section in the
  manual](http://php.net/manual/en/function.assert.php); both values should be
  set to `1`.

## Installation
```sh
composer require --dev gentry/gentry
```

You can now run `vendor/bin/gentry`.

## Configuration
Create a `Gentry.json` file in the root of your project. It accepts the
following options:

```json
{
    "src": "/path/to/src",
    "test": "/path/to/testrunner/executable",
    "ignore": "some.*?regex",
    "bootstrap": "/path/to/file"
}
```

### string|array `src` ###
Path(s) to your source files. Can be either absolute or relative to project
root - hence`"/path/to/root/src"` could be simplified to just `"src"`. If you
have multiple source paths you may define an array of strings.

Directories are recursed automatically.

### string `test` ###
The command you use to run your (unit)tests for PHP normally (e.g.
`vendor/bin/phpunit`).

### string|array `bootstrap` ###
The path(s) to file(s) ("bootstrapper(s)") every piece of code in your
application needs. This is usually something that would reside in an `index.php`
entry point or similar file. These files are otherwise ignored by Gentry when
analysing your code and should do stuff like initialise an autoloader.

You can also pass an array of files instead of a string. They will be prepended
in order.

> Caution: if `bootstrap`ped files reside inside `src`, they won't be ignored.
> Gentry uses `require_once` of course, but if these files contain testable
> features it will try and do something sensible with them.

This isn't necessarily a bad thing; you could actually write tests that test the
mock objects you use in other tests :)

### string `ignore` ###
A regular expression of classnames to ignore in the `"src"` path. Useful for
automatically ignoring classtypes that are hard to test, e.g. controllers. You
could also utilise this if your tests and sourcecode are mixed in the same
directory (some frameworks like that kind of thing).

## CLI usage
Now run Gentry from the command line and see what happens:

```sh
vendor/bin/gentry
```

It will complain about zero code coverage, even if you already defined a bunch
of tests. Wait, wut? Well, you _do_ need to tell Gentry what you've already
written tests for.

In your tests, instead of simply creating/using stuff, you'll need to build a
_wrapped entity_. Entities wrapped by Gentry are "Gentry aware". Don't worry
about the rest of your tests; apart from logging features tested the wrapped
entities are fully compatible. E.g. wrapping a `Foo` object will still pass
`$foo instanceof Foo` type tests. (The only place where this would fail is if
you actually use `get_class` and test for string equality - but that would be
kind of stupid, right?)

To create wrapped entities, we use the `Gentry\Gentry\Wrapper` utility class.

## Wrapping objects
```php
<?php

// Instead of this...
$foo = new Foo;
// ...do this:
$foo = Gentry\Gentry\Wrapper::createObject(Foo::class);
```

If your object's constructor takes arguments, you can supply them as additional
arguments to the `createObject` method.

Try it in a class for one of your tests and watch the code coverage increase!


