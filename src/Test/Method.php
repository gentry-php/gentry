<?php

namespace Gentry\Test;

use Reflector;
use ReflectionMethod;
use ReflectionException;
use Exception;

class Method extends Feature
{
    private $class;

    public function __construct($target, $name, $class)
    {
        parent::__construct($target, $name);
        $this->class = $class;
    }

    public function actual(array $args)
    {
        $actual = ['thrown' => null, 'out' => null];
        try {
            $target = is_null($args[$this->target]) ?
                $this->class :
                $args[$this->target];
            $feature = new ReflectionMethod($target, $this->name);
        } catch (ReflectionException $e) {
            $this->messages[] = sprintf(
                "<red>ERROR: <gray>No such method <magenta>%s::%s",
                $this->tostring($args[$this->target]),
                $this->name
            );
            return $actual;
        }
        ob_start();
        try {
            $actual['result'] = $feature->invokeArgs(
                $args[$this->target],
                array_slice($args, 1)
            );
        } catch (Exception $e) {
            $actual['thrown'] = $e;
        }
        $actual['out'] = \Gentry\cleanOutput(ob_get_clean());
        return $actual;
    }
}

