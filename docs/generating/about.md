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

