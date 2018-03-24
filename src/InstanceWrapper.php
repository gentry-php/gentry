<?php

namespace Gentry\Gentry;

/**
 * Like `ClassWrapper`, only for already-instantiated objects. Mostly used
 * internally and automatically by `Wrapper::wrapObject`.
 */
trait InstanceWrapper
{
    use ClassWrapper;

    private $__gentryInheritedObject;

    /**
     * @param object $object
     * @param mixed ...$args
     * @return void
     */
    public function __gentryConstructFromInheritedObject(object $object, ...$args) : void
    {
        $this->__gentryConstruct(...$args);
        $this->__gentryInheritedObject = $object;
    }

    /**
     * @param string $prop
     * @return mixed
     */
    public function __get(string $prop)
    {
        return $this->__gentryInheritedObject->$prop;
    }

    /**
     * @param string $prop
     * @param mixed $value
     * @return mixed
     */
    public function __set(string $prop, $value)
    {
        $parent = get_parent_class($this);
        return $this->__gentryInheritedObject->$prop = $value;
    }

    /**
     * @param string $prop
     * @return bool
     */
    public function __isset(string $prop) : bool
    {
        return isset($this->__gentryInheritedObject->$prop);
    }
}

