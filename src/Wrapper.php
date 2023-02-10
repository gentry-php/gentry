<?php

namespace Gentry\Gentry;

use ReflectionClass, ReflectionMethod, ReflectionProperty, ReflectionException;
use JsonSerializable;
use ArrayAccess;
use Stringable;
use Throwable;

/**
 * Wrapper class for logging method calls.
 */
class Wrapper implements JsonSerializable, ArrayAccess, Stringable
{
    public function __construct(
        private object|string $wrapped,
        private ?int $methodFilter = null,
        private ?int $propertyFilter = null
    ) {}

    public function __call(string $name, array $args) : mixed
    {
        $reflectionMethod = array_values(array_filter(
            (new ReflectionClass($this->wrapped))->getMethods($this->methodFilter),
            fn ($method) => $method->name === $name
        ))[0] ?? null;
        if (!$reflectionMethod) {
            throw new MethodDoesNotExistException($this->wrapped, $name);
        }
        $reflectionMethod->setAccessible(true);
        $attributes = $reflectionMethod->getAttributes(Untestable::class);
        if (!$attributes) {
            Logger::getInstance()->logFeature($this->wrapped, $name, $args);
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
            throw new MethodCouldNotBeInvokedException($this->wrapped, $name, $e);
        }
    }

    public static function __callStatic(string $method, array $args) : mixed
    {
        throw new StaticMethodsNotSupportedException(
            "The method $method was called statically, which isn't supported.\n
Simply call the method on the Wrapper instance, and it will forward the call statically."
        );
    }

    public function __isset(string $name) : bool
    {
        $property = array_values(array_filter(
            (new ReflectionClass($this->wrapped))->getProperties($this->propertyFilter),
            fn ($property) => $property->name === $name
        ))[0] ?? null;
        if (isset($property)) {
            return $property->getValue($this->wrapped) !== null;
        } else {
            // Probably meant to forward to the wrapped __isset;
            // throw an error otherwise.
            return isset($this->wrapped->$name);
        }
        return isset($property);
    }

    public function __get(string $name) : mixed
    {
        $property = array_values(array_filter(
            (new ReflectionClass($this->wrapped))->getProperties($this->propertyFilter),
            fn ($property) => $property->name === $name
        ))[0] ?? null;
        if (isset($property)) {
            $property->setAccessible(true);
            return $property->getValue($this->wrapped);
        } else {
            // Probably meant to forward to the wrapped __get;
            // throw an error otherwise.
            return $this->wrapped->$name;
        }
    }

    public function __set(string $name, mixed $value) : void
    {
        $property = array_values(array_filter(
            (new ReflectionClass($this->wrapped))->getProperties($this->propertyFilter),
            fn ($property) => $property->name === $name
        ))[0] ?? null;
        if (isset($property)) {
            // This is deliberate; if the property is non-public, we must
            // assume the wrapped object supplies a magic `__set` method.
            $this->wrapped->$name = $value;
        } else {
            throw new PropertyDoesNotExistException($this->wrapped, $name);
        }
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

