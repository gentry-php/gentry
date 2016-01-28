<?php

namespace Gentry\Test;

use Reflector;
use ReflectionMethod;
use ReflectionException;
use Exception;

class Method extends Feature
{
    public function actual(array $args)
    {
        $actual = ['thrown' => null, 'out' => null];
        try {
            $feature = new ReflectionMethod($args[$this->target], $this->name);
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
                is_string($args[$this->target]) ?
                    null :
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

