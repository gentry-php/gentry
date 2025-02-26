<?php

namespace Gentry\Gentry;

use DomainException;
use Throwable;

class MethodCouldNotBeInvokedException extends DomainException
{
    public function __construct(object|string $wrapped, string $method, ?Throwable $previous = null)
    {
        $class = is_object($wrapped) ? get_class($wrapped) : $wrapped;
        parent::__construct(
            sprintf(
                "The method $class::$method could not be invoked, with error %s",
                $previous ? $previous->getMessage() : '[unknown]'
            ),
            0,
            $previous
        );
    }
}

