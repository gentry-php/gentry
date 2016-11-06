<?php

namespace Gentry\Gentry;

use Throwable;
use ReflectionClass;
use ReflectionMethod;

trait ClassWrapper
{
    private $__gentryInstance;
    private static $__gentryStaticInstance;
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
        self::$__gentryStaticInstance = $instance;
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
        if (!isset($class)) {
            $class = (new ReflectionClass(get_called_class()))
                ->getParentClass()
                ->name;
        }
        $logger = Logger::getInstance();
        $reflection = new ReflectionMethod($class, $method);
        $args = array_map(function ($arg) {
            return gettype($arg);
        }, $args);
        $params = $reflection->getParameters();
        if (count($params) > count($args)) {
            for ($i = count($args); $i < count($params); $i++) {
                $args[] = gettype($params[$i]->getDefaultValue());
            }
        }
        $logger->logFeature($class, $method, $args);
    }
}

