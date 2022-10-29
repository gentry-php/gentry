<?php

namespace Gentry\Gentry;

use ReflectionMethod;
use ReflectionProperty;
use ReflectionException;
use JsonSerializable;
use ArrayAccess;
use Stringable;

/**
 * Wrapper class for logging method calls.
 */
class Wrapper implements JsonSerializable, ArrayAccess, Stringable
{
    public function __construct(
        private object|string $wrapped
    ) {}

    public function __call(string $method, array $args) : mixed
    {
        try {
            $reflectionMethod = new ReflectionMethod($this->wrapped, $method);
        } catch (ReflectionException $e) {
            throw new MethodDoesNotExistException($this->wrapped, $method);
        }
        $attributes = $reflectionMethod->getAttributes(Untestable::class);
        if (!$attributes) {
            Logger::getInstance()->logFeature($this->wrapped, $method, $args);
        }
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
            return $reflectionMethod->invokeArgs($reflectionMethod->isStatic() ? null : $this->wrapped, $arguments);
        } catch (ReflectionException $e) {
            throw new MethodCouldNotBeInvokedException($this->wrapped, $method);
        }
    }

    public static function __callStatic(string $method, array $args) : mixed
    {
        throw new StaticMethodsNotSupportedException(
            "The method $method was called statically, which isn't supported.\n
Simply call the method on the Wrapper instance, and it will forward the call statically."
        );
    }

    public function __get(string $name) : mixed
    {
        $property = new ReflectionProperty($this->wrapped, $name);
        $property->setAccessible(true);
        return $property->getValue($this->wrapped);
    }

    public function __set(string $name, mixed $value) : void
    {
        $property = new ReflectionProperty($this->wrapped, $name);
        $property->setAccessible(true);
        $property->setValue($this->wrapped, $value);
    }

    public function __toString() : string
    {
        if ($this->wrapped instanceof Stringable) {
            return "{$this->wrapped}";
        }
        return '';
    }

    public function jsonSerialize() : mixed
    {
        if (!($this->wrapped instanceof JsonSerializable)) {
            return $this->wrapped;
        }
        return $this->__call('jsonSerialize', []);
    }

    public function offsetExists(mixed $offset) : bool
    {
        return $this->wrapped instanceof ArrayAccess && isset($this->wrapped[$offset]);
    }

    public function offsetGet(mixed $offset) : mixed
    {
        return $this->wrapped instanceof ArrayAccess ? $this->wrapped[$offset] : null;
    }

    public function offsetSet(mixed $offset, mixed $value) : void
    {
        if ($this->wrapped instanceof ArrayAccess) {
            $this->wrapped[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset) : void
    {
        if ($this->wrapped instanceof ArrayAccess) {
            unset($this->wrapped[$offset]);
        }
    }
}

