# Gentry
PHP unit test generation tools

Good programmers are lazy, but unfortunately that means that stuff like writing
unit tests (boooooring) is often skipped. Please don't; it's important and oh
so handy once you have them in place.

For the slackers, but also if you're inheriting a testless legacy project,
Gentry can generate test skeletons.

## Installation

### Composer (recommended)
```composer require monomelodies/gentry```

You can now run `vendor/bin/gentry`.

> You'll probably want to put this in your `require-dev` section.

### Manual
Download or clone the repo. There's an executable in the root.

## Usage

Gentry makes two assumptions:

    1. Your source files are in a directory.
    2. Your tests are in another directory.

If these two are mixed, clean that up first. Seriously.

You can run Gentry without arguments to see all options. Normal usage is as
follows:

```vendor/bin/gentry COMMAND --code /path/to/code --tests /path/to/tests```

Available commands are `test` (just analyse), `dryrun` (output what Gentry will
attempt to do without doing it) and `add` (add tests).

Gentry ignores all classes that are abstract. Extending classes should perform
tests. It also expects existing tests to see if they have the `@covers`
annotation. It's a good idea to add those; in fact, Gentry refuses to do
anything if they're missing. This is because it's virtually impossible to define
what's being tested, which might lead to duplicates. If there's no class
methods the test `@covers`, just add something random (`@covers noop`).

Gentry also ignores `private` or `protected` methods, methods added via a
trait or by extending another class, and of course classless files. In cases
where these methods _do_ need to be tested, you should add them manually.

> This is especially true when working with some base class that has methods
> that need to be tested. Take care here. The underlying assumption is that
> methods defined _on_ a class contain the logic you need to test foremost.

## Options

- #### -c|--code ####

    Path to your source code.

- #### -t|--tests ####

    Path to your unit tests.

- #### -v|--verbose ####

    Be verbose. Useful if something goes wrong.

- #### -i|--include_path ####

    Use a custom `include_path`. Useful if your code depends on it for
    autoloading.

- #### -s|--strip ####

    Regex to strip off the beginning of paths before attempting
    `require_once`. Useful if your code depends on the `include_path` to
    magically override classes (otherwise, PHP will error out due to
    "class already defined").

- #### -b|--base ####

    Class to use as base class for tests (default:
    `PHPUnit_Framework_TestCase`). It's up to you to make sure this actually
    exists, Gentry won't generate it for you of course.

- #### -p|--prepend ####

    PHP file to prepend ("bootstrapper"). Sometimes you can't run your code
    using just Composer's autoloader.

## Common gotchas

### Fatal errors
If Gentry seems to stop midway through your tests, run with the `--verbose`
option and inspect if any PHP file is causing a fatal error.

### Existing tests need extending
Gentry won't overwrite existing files. It will warn you, however. Use the
`dryrun` command to inspect what it wants to write, and add it manually.

### After inspection, not all classes mentioned are generated
Usually caused by some test doing a manual `include` or `require`. Since the
file in question was already included by the time Gentry inspects your code
base, the contained class already exists and isn't flagged for inspection.
Find the test file responsible, and make sure any `include`s aren't called
immediately, e.g. by wrapping them in a function:

```php
<?php

// Wrong:
require '/path/to/some/file.php';

class MyTest extends PHPUnit_Framework_TestCase
{
}

```

```php
<?php

// Correct:
class MyTest extends PHPUnit_Framework_TestCase
{
    pubic function __construct()
    {
        parent::__construct();
        require '/path/to/some/file.php';
    }
}

```

