<?php

namespace Gentry;

use DomainException;

/**
 * Exception thrown when an author forgets to specify what to test...
 */
class NoTestableObjectProvidedException extends DomainException
{
}

