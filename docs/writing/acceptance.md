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

## Sharing data
Not all data gets stored in a database. For instance, you might want to write an
acceptance test for a signup page, and verify in the test that a mail was sent
to the user signing up (for confirmation purposes). Obviously we don't want to
actually send an email (let alone connect to an actual mailbox to retrieve it).
For this you can use Gentry's super-simple `Cache` classes.

> While the Gentry cache is fully PSR-compliant, it is _not_ intended for use
> outside of Gentry. The storage is strictly per-run and it doesn't do anything
> like file locking since normally you wouldn't need that during testing (as
> opposed to on, say, a high traffic application).

### Step 1: intercept what you need
This is up to you and your implementation, but the idea is that whenever
something goes "outside" your application (like an email, but could also be a
call to or from an external API) you implement a _mock_ handler for that. Inside
the mock, instead of performing the intended action store the intended result
(or whatever value you're going to need to check on later) in the cache pool.
An example:

```php
function mockMailer($to, $message)
{
    Gentry\Cache\Pool::getInstance()
        ->save(new Gentry\Cache\Item('mail', <<<EOT
To: $to
Message: $message
EOT
        ));
}
```

### Step 2: assert the cached data
Then, in your actual test, retrieve the cached item:

```php
// ...
$item = Gentry\Cache\Pool::getInstance()->getItem('mail');
yield asset($item->get() == <<<EOT
To: john@doe.com
Message: Howdy!
EOT
);
```

Note that per PSR-6, items are stored in the cache wrapped in an object
implementing `Psr\Cache\CacheInterface`. Use the `get` method to retrieve the
actual contents.

Also note that - Gentry's cache being extremely simple - anything you store will
be serialized/deserialized. So take care not to store stuff like database
handles (or implement proper `__sleep`/`__wakeup` methods).

