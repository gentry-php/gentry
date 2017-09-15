<?php

namespace Gentry\Gentry;

use Throwable;
use ReflectionClass;
use ReflectionMethod;

require_once 'helpers.php';

trait ClassWrapper
{
    private static $__gentryConstructionArguments;

    public function __construct()
    {
        if (isset(self::$__gentryConstructionArguments)) {
            if (method_exists(get_parent_class($this), '__construct')) {
                parent::__construct(...self::$__gentryConstructionArguments);
            }
        }
    }

    public function __gentryConstruct(...$args)
    {
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
        if (!$class) {
            $class = (new ReflectionClass(get_called_class()))
                ->getParentClass()
                ->name;
        }
        $logger = Logger::getInstance();
        $reflection = new ReflectionMethod($class, $method);
        $args = array_map(function ($arg) {
            return getNormalisedType($arg);
        }, $args);
        $logger->logFeature($class, $method, $args);
    }
}

