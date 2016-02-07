# Acceptance tests
One notch up from integration tests, acceptance tests should test your entire
application. That means actually using a web browser for most web applications.

> One could argue that a CLI test is also a form of an acceptance test, with the
> command line acting as a stand-in for a browser. Semantics, semantics...

Gentry is written in PHP which (duh) gets compiled on the server. Websites
consist of HTML, CSS and Javascript. Hence, you'll need a headless browser like
PhantomJS to run your acceptance tests.

## Prerequisites
First and foremost, we're going to need the PhantomJS headless browser. Luckily,
we can install it via Composer!

> Installing PhantomJS requires the PHP BZ2 module.

Add the following to (the root of) your application's `composer.json`:

```json
"scripts": {
    "post-install-cmd": [
        "PhantomInstaller\\Installer::installPhantomJS"
    ],
    "post-update-cmd": [
        "PhantomInstaller\\Installer::installPhantomJS"
    ]
}
```

You could also call this manually, but this is handy. Run `composer update`. The
`phantomjs` binary is now installed into your `vendor/bin` directory. Create a
`bin` directory in your application's root if you don't have one yet, and
symlink the executable there.

> This is needed due to an apparent bug in the PhpPhantomJs package. It's
> _supposed_ to get its location from Composer, only it seems to be hardcoded.

## Preparing your project
Your project needs to be made Gentry-aware. For regular tests we did that via
the `getenv('GENTRY')` check; for HTTP calls it is similar. Gentry's browser
passes these variables in headers, and they are thus available as
`$_SERVER['HTTP_GENTRY_something']` entries in the server superglobal.

## Writing an acceptance test
To write these tests, we'll make use of the `Gentry\Browser` object. This is a
wrapper around PHP PhantomJS with some convenience methods.

```php
<?php

class Test
{
    /**
     * Going to grab an external page
     */
    public function getAPage()
    {
        $browser = new Gentry\Browser;
        yield asset($browser->get('http://example.com/')->getStatus() == 200);
    }
}
```

The `get` and `post` methods on the Browser return a "response" object. This
tells us stuff about the page we just retrieved, like the HTTP status code we
were checking in this example. You can also get the full page contents, inspect
all headers etc. See the PHP PhantomJS API documentation for all options.

## Sessions and cookies
Gentry sets a "fake" session id with the same value as the "client id" used for
the test run (a 6 character mini-hash). The default session name is `PHPSESSID`.
You can override these if you need to;

```php
<?php

// ...
        Gentry\Browser::setSession('my-name', 'my-id');
        $browser = new Gentry\Browser;
// ...
```

The ID parameter is optional since it's probably only relevant if your custom
session does some insane validity checking on it.

> When passing a custom id, it's up to you to make sure your test script and
> your online application can somehow access the same pool.

