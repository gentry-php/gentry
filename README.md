# Gentry
Test generation tools for PHP8+.

Good programmers are lazy, but unfortunately that means that stuff like writing
tests (boooooring) is often skipped. Please don't; it's important and oh so
handy once you have them in place.

Gentry was designed to make writing tests _so_ easy even the worst slacker will
bother, and to alleviate writing boilerplate code by generating skeletons for
you.

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
    "bootstrap": "/path/to/file",
    "generator": "Fully\\Qualified\\Namespace",
    "output": "/path/to/directory",
    "namespace": "Your\\Preferred\\Namespace"
}
```

### string|array `src` ###
Path(s) to your source files. Can be either absolute or relative to project
root - hence`"/path/to/src"` could be simplified to just `"src"`. If you have
multiple source paths you may define an array of strings.

Directories are recursed automatically.

### string `test` ###
The command you use to run your (unit)tests for PHP normally (e.g.
`vendor/bin/phpunit`).

### string|array `ignore` ###
(A) regular expression(s) of classnames to ignore in the `"src"` path. Useful for
automatically ignoring classtypes that are hard to test, e.g. controllers. You
could also utilise this if your tests and sourcecode are mixed in the same
directory (some frameworks like that kind of thing).

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

### string `generator` ###
The "generator" used when creating stub tests. For instance, for the
`gentry/toast` plugin this would be `Gentry\\Toast`. More about these plugins
below. Note that `Gentry\\Toast` in this example is the _namespace_; by default,
the actual class should be called `Generator` and extend the abstract base class
`Gentry\Gentry\Generator`.

### string `output` ###
The directory where generated tests will be written to. No files will be
overwritten; if a guesstimated filename already exists, it will be suffixed
with `.1` (or `.2` etc.).

## CLI usage
Now run Gentry from the command line and see what happens:

```sh
$ vendor/bin/gentry analyze
```

It will complain about zero code coverage, even if you already defined a bunch
of tests. Wait, wut? Well, you _do_ need to tell Gentry what you've already
written tests for.

## Modifying existing tests for Gentry compatibility
In your tests, instead of simply creating/using stuff, you'll need to build a
_wrapped entity_. Entities wrapped by Gentry are "Gentry aware".

To create wrapped entities, we use the `Gentry\Gentry\Wrapper` utility class.

### Wrapping objects
```php
<?php

function myTest()
{
    // Instead of this...
    $foo = new Foo;
    // ...do this:
    $foo = new Gentry\Gentry\Wrapper(new Foo);

    // or however your testing framework asserts stuff...
    assert($foo->someMethod() === true);
}
```

Try it in one of your tests and watch the code coverage increase!

All method calls and properties are proxied to the original object, so just do
whatever you wanted to do as when `$foo` was actually an instance of `Foo`. The
only thing you _cannot_ do is pass it to other methods that actually expect a
`Foo`. But that would be silly testing anyway; if you're testing
`Bar::someOtherMethod` you're not testing `Foo`, so that should be its own test
(where `Bar` is wrapped instead). Keep it clean, folks!

Note: the proxying of methods/properties extends to inaccessible ones, using
reflection. This is because you might want to test if some internal state was
set correctly. But, ideally one would only need to test public stuff. Gentry
only attempts to generate tests for _public_ methods to begin with. If a
protected or private method is called on a wrapped object, it has no effect on
the tests being generated, even though Gentry internally will know about it.

## Generating tests
If you'd mostly like to see what Gentry would propose to do, run the following:

```sh
$ vender/bin/gentry show
```

This will output all generated tests to STDOUT. Happy with what you see? Then
you can run:

```sh
$ vendor/bin/gentry generate
```

## Example using Toast
Let's show an example of generating tests for the [Toast test
runner](https://packagist.org/packages/toast/unit) (since that's what I
usually choose - I wrote it, after all ;)). Gentry offers a pre-built template
for this! Install it first:

```sh
composer require --dev gentry/toast
```

Next we configure it:

```json
{
    //...
    generator: "Gentry\\Toast"
    //...
}
```

That's all! Well, unless you want a namespace of course, but Toast tests consist
of lambdas so it's generally not needed.

The test file names are guesstimated based on the class names; you'll probably
want to do some regrouping to keep things organized. But hey, at least you can
copy/paste the boilerplate!

## Writing custom Generators
The base Generator contains two abstract methods you must implement:

```php
abstract protected function convertTestNameToFilename(string $name) : string;

abstract protected function getTemplatePath() : string;
```

`convertTestNameToFilename` takes the class name of the object under test, and
converts that to a filename. Subdirectories are currently not supported, in the
sense that creating them is up to you.

`getTemplatePath` returns the path of your Twig templates. One template should
at least be available: `template.html.twig`. Of course, you're free to split out
stuff into smaller templates using standard Twig `{% extends %}` and
`{% include %}` functionality. Take a look at `gentry/toast` for an example.

And... that's it, really. Your `template.html.twig` will receive three
variables:

- `namespace`: the namespace defined in the config file, or `null`.
- `objectUnderTest`: the class name of the object under test.
- `features`: an array of features to test (i.e., the public methods).

`features` contains an array with method names as the keys, and a `stdClass`
with a `calls` property containing an array in the following form:

```php
<?php

$this->features[$method->name]->calls[] = (object)[
    'name' => $method->name,
    'parameters' => implode(', ', $arglist),
    'expectedResult' => $expectedResult,
    'isStatic' => $method->isStatic(),
];
```

Note that the `parameters` key contains a string; you can just inject this
verbatim when "calling" your method in the template.

