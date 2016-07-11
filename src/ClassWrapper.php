<?php

namespace Gentry\Gentry;

use Throwable;
use ReflectionClass;

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

    public static function __gentryLogMethodCall($method)
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
}

