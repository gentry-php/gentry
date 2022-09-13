<?php

namespace Gentry\Gentry;

/**
 * Wrapper class for logging method calls.
 */
class Wrapper
{
    public function __construct(private object $wrapped) {}

    public function __call(string $method, array $args) : mixed
    {
        $logger = Logger::getInstance();
        $logger->logFeature($this->wrapped, $method, $args);
        return $this->wrapped->$method(...$args);
    }

    public static function __callStatic(string $method, array $args) : mixed
    {
        $logger = Logger::getInstance();
        $logger->logFeature($this->wrapped, $method, $args);
        return $this->wrapped::$method(...$args);
    }
}

