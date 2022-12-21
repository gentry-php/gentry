<?php

namespace Gentry\Gentry;

use DomainException;

class PropertyDoesNotExistException extends DomainException
{
    public function __construct(object|string $wrapped, string $property)
    {
        $class = is_object($wrapped) ? get_class($wrapped) : $wrapped;
        parent::__construct("The property $class::$property does not exist");
    }
}

