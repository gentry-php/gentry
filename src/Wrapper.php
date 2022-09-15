<?php

namespace Gentry\Gentry;

use ReflectionMethod;
use ReflectionProperty;

/**
 * Wrapper class for logging method calls.
 */
class Wrapper
{
    public function __construct(
        private object|string $wrapped
    ) {}

    public function __call(string $method, array $args) : mixed
    {
        $logger = Logger::getInstance();
        $logger->logFeature($this->wrapped, $method, $args);
        $method = new ReflectionMethod($this->wrapped, $method);
        $method->setAccessible(true);
        return $method->invoke($this->wrapped, ...$args);
    }

    public static function __callStatic(string $method, array $args) : mixed
    {
        // Since we use reflection, this is now identical.
        return $this->__call($method, $args);
    }

    public function __get(string $name) : mixed
    {
        $property = new ReflectionProperty($this->wrapped, $name);
        $property->setAccessible(true);
        return $property->getValue($this->wrapped);
    }
}

