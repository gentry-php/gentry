# Unit tests
Unit tests test exactly one feature in all applicable scenarios, e.g. the method
`bar` on class `Foo`. They'll only inject one parameter and all test
descriptions will refer to `"{0}"`.

Of course it's perfectly valid to also test properties on the object under test
before and/or after `yield`ing expected results.

Grouping-wise, it usually make sense to place all unit tests for methods on a
single class in one test class named after the class you're testing (what a
mouthfull!).

E.g. for a class `Foo` with methods `bar` and `baz` you might have a test class
`TestFoo` with methods `testBar` and `testBaz`.

Use unit tests to assure that, in isolation, methods do what they should.

> Tip: if a unit test's description only contains one instance of `"{0}"`, you
> can omit it altogether. Gentry will assume you meant to end your description
> with `"{0}"`.

