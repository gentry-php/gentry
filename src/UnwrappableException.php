<?php

namespace Gentry\Gentry;

use DomainException;

class UnwrappableException extends DomainException
{
    public const NON_EXISTANT = 1;

    public const IS_INTERFACE = 2;

    public function __construct(string $class, int $code)
    {
        $msg = '';
        switch ($code) {
            case self::NON_EXISTANT:
                $msg = "Cannot wrap non-existant class $class.";
                break;
            case self::IS_INTERFACE:
                $msg = "Cannot wrap interface $class; please supply an implementation.";
                break;
        }
        parent::__construct($msg, $code);
    }
}

