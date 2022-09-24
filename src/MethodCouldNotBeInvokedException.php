<?php

namespace Gentry\Gentry;

use DomainException;

class MethodCouldNotBeInvokedException extends DomainException
{
    public function __construct(object|string $wrapped, string $method)
    {
        $class = is_object($wrapped) ? get_class($wrapped) : $wrapped;
        parent::__construct("The method $class::$method could not be invoked");
    }
}

