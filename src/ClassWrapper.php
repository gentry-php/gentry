<?php

namespace Gentry;

use Throwable;
use ReflectionClass;

trait ClassWrapper
{
    public function setGentryInstance($object)
    {
        $this->gentryInstance = $object;
    }

    public static function logGentryMethodCall($method)
    {
        static $logger;
        if (!isset($logger)) {
            $logger = Logger::getInstance();
        }
        $instance = (new ReflectionClass(get_called_class()))
            ->getParentClass()
            ->name;
        $logger->logFeature(Logger::METHOD, [$instance, $method]);
    }

    public function __get($property)
    {
        return $this->gentryInstance->{$property};
    }

    public function __isset($property)
    {
        return isset($this->gentryInstance->{$property});
    }
}

