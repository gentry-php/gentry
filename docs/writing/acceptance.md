# Acceptance tests
One notch up from integration tests, acceptance tests should test your entire
application. That means actually using a web browser for most web applications.

> One could argue that a CLI test is also a form of an acceptance test, with the
> command line acting as a stand-in for a browser. Semantics, semantics...

Gentry is written in PHP which (duh) gets compiled on the server. Websites
consist of HTML, CSS and Javascript. Hence, you'll need a headless browser like
PhantomJS to run your acceptance tests.

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


