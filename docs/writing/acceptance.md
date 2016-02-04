# Acceptance tests
One notch up from integration tests, acceptance tests should test your entire
application. That means actually using a web browser for most web applications.

> One could argue that a CLI test is also a form of an acceptance test, with the
> command line acting as a stand-in for a browser. Semantics, semantics...

Gentry is written in PHP which (duh) gets compiled on the server. Websites
consist of HTML, CSS and Javascript. Hence, you'll need a headless browser like
PhantomJS to run your acceptance tests.

Acceptance testing is planned for version 0.7.

