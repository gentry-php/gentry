<?php

namespace Gentry\Gentry;

trait InstanceWrapper
{
    use ClassWrapper;

    private $__gentryInheritedObject;

    public function __gentryConstructFromInheritedObject($object, ...$args)
    {
        $this->__gentryConstruct(...$args);
        $this->__gentryInheritedObject = $object;
    }

    public function __get(string $prop)
    {
        return $this->__gentryInheritedObject->$prop;
    }

    public function __set(string $prop, $value)
    {
        $parent = get_parent_class($this);
        return $this->__gentryInheritedObject->$prop = $value;
    }

    public function __isset(string $prop) : bool
    {
        return isset($this->__gentryInheritedObject->$prop);
    }
}

