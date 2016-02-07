<?php

namespace Gentry;

use Throwable;
use ReflectionClass;

trait ClassWrapper
{
    public function __construct()
    {
    }

    public function __gentryConstruct(...$args)
    {
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

