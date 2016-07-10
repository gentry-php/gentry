<?php

namespace Gentry\Gentry;

use Throwable;
use ReflectionClass;

trait ClassWrapper
{
    public function __construct()
    {
        if (isset(self::$__gentryConstructionArguments)) {
            parent::__construct(...self::$__gentryConstructionArguments);
        }
    }

    public function __gentryConstruct(...$args)
    {
        self::$__gentryConstructionArguments = $args;
        try {
            parent::__construct(...$args);
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

