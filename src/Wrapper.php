<?php

namespace Gentry\Gentry;

use ReflectionMethod;
use ReflectionProperty;
use ReflectionException;

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
        try {
            $reflectionMethod = new ReflectionMethod($this->wrapped, $method);
        } catch (ReflectionException $e) {
            throw new MethodDoesNotExistException($this->wrapped, $method);
        }
        $logger->logFeature($this->wrapped, $method, $args);
        $arguments = [];
        foreach ($reflectionMethod->getParameters() as $i => $parameter) {
            if (!isset($args[$i])) {
                break;
            }
            if ($parameter->isPassedByReference()) {
                $arguments[] =& $args[$i];
            } else {
                $arguments[] = $args[$i];
            }
        }
        try {
            return $reflectionMethod->invokeArgs($this->wrapped, $arguments);
        } catch (ReflectionException $e) {
            throw new MethodCouldNotBeInvokedException($this->wrapped, $method);
        }
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

