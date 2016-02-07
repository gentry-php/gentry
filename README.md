# Gentry
A testing framework for PHP7+

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
    "bootstrap": "/path/to/bootstrap.php",
    "namespace": "Foo",
    "ignore": "some.*?regex"
}
```

### string|array `src` ###
### string `tests` ###
Both `src` and `tests` can be either absolute, or relative to the root - hence
`"/path/to/root/src"` could be simplified to just `"src"`.

Directories are recursed. If Gentry detects that `tests` is inside `src`, it
skips it for you (but seriously, don't do that).

Gentry supports multiple `src` directories, but only one `tests` directory. The
simple reasoning is that it's not uncommon to place scripts in a `bin` directory
outside of `src`, but still have them tested.

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

> Caution: if `bootstrap`ped files reside inside `src`, they won't be ignored.
> Gentry uses `require_once` of course, but if these files contain testable
> features it will try and do something sensible with them.

This isn't necessarily a bad thing; you could actually write tests that test the
mock objects you use in other tests :)

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

## Detecting the environment
For a lot of testing, you'll need to detect whether or not to use a mock object
(e.g. for database connections), or "the real thang". The simplest way is to
check `getenv("GENTRY")` where needed. Gentry's executable sets that for you, so
it's a sure-fire way of knowing you're in testing mode. Unless you're using that
same environment variable yourself somewhere. But that would be silly.

## Generating missing tests
Run Gentry with the `-g` flag to generate skeletons for missing tests for you:

```sh
vendor/bin/gentry -g
```

More on generating tests in the corresponding section of the manual.

