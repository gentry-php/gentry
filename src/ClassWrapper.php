<?php

namespace Gentry\Gentry;

use Throwable;
use Monomelodies\Reflex\ReflectionClass;
use Monomelodies\Reflex\ReflectionMethod;

require_once 'helpers.php';

/**
 * Trait to make classes "analyzable" internally. Typically one would call
 * `Gentry\Gentry\Wrapper::wrapObject` or the like.
 */
trait ClassWrapper
{
    /**
     * Constructor delegate.
     *
     * @param mixed ...$args
     * @return void
     */
    public function __gentryConstruct(...$args) : void
    {
        try {
            if (method_exists(get_parent_class($this), '__construct')) {
                parent::__construct(...$args);
            }
        } catch (Throwable $e) {
        }
    }        

    /**
     * Logs a method call so Gentry can determine untested methods. Mostly used
     * internally by gentry.
     *
     * @param string $method Method name
     * @param string|null $class Optional classname. Default to the parent of
     *  the current class.
     * @param array $args Optional array of arguments.
     * @return void
     */
    public static function __gentryLogMethodCall(string $method, string $class = null, array $args = []) : void
    {
        if (!$class) {
            $class = (new ReflectionClass(get_called_class()))
                ->getParentClass()
                ->name;
        }
        $logger = Logger::getInstance();
        $reflection = new ReflectionMethod($class, $method);
        array_walk($args, function (&$arg, $i) use ($reflection) {
            $arg = $reflection->getParameters()[$i]->getNormalisedType($arg);
        });
        $logger->logFeature($class, $method, $args);
    }
}

