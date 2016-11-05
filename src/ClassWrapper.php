<?php

namespace Gentry\Gentry;

use Throwable;
use ReflectionClass;

trait ClassWrapper
{
    private $__gentryInstance;
    private static $__gentryConstructionArguments;

    public function __construct()
    {
        if (isset(self::$__gentryConstructionArguments)) {
            if (method_exists(get_parent_class($this), '__construct')) {
                parent::__construct(...self::$__gentryConstructionArguments);
            }
        }
    }

    public function __gentryConstruct($instance, ...$args)
    {
        $this->__gentryInstance = $instance;
        self::$__gentryConstructionArguments = $args;
        try {
            if (method_exists(get_parent_class($this), '__construct')) {
                parent::__construct(...$args);
            }
        } catch (Throwable $e) {
        }
    }        

    public static function __gentryLogMethodCall($method, $class = null, array $args = [])
    {
        static $logger;
        if (!isset($logger)) {
            $logger = Logger::getInstance();
        }
        if (!isset($class)) {
            $class = (new ReflectionClass(get_called_class()))
                ->getParentClass()
                ->name;
        }
        $args = array_map(function ($arg) {
            return gettype($arg);
        }, $args);
        $logger->logFeature($class, $method, $args);
    }
}

