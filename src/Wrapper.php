<?php

namespace Gentry\Gentry;

/**
 * Wrapper class for logging method calls.
 */
class Wrapper
{
    private static $staticWrapped;

    public function __construct(private object $wrapped)
    {
        self::$staticWrapped = get_class($wrapped);
    }

    public function __call(string $method, array $args) : mixed
    {
        $logger = Logger::getInstance();
        $logger->logFeature($this->wrapped, $method, $args);
        return $this->wrapped->$method(...$args);
    }

    public static function __callStatic(string $method, array $args) : mixed
    {
        $logger = Logger::getInstance();
        $logger->logFeature(self::$staticWrapped, $method, $args);
        return self::$staticWrapped::$method(...$args);
    }
}

