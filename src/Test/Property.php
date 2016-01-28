<?php

namespace Gentry\Test;

use ErrorException;
use Exception;

class Property extends Feature
{
    public function actual(array $args)
    {
        $actual = ['thrown' => null, 'out' => null];
        ob_start();
        $class = $args[0];
        $property = $this->name;
        try {
            $actual['result'] = is_object($class) ?
                $class->$property :
                $class::$property;
        } catch (ErrorException $e) {
            $this->messages[] = sprintf(
                "<red>ERROR: <gray>No such property <magenta>%s::$%s",
                is_string($class) ? $class : get_class($class),
                $this->name
            );
        } catch (Exception $e) {
            $actual['thrown'] = $e;
        }
        $actual['out'] = \Gentry\cleanOutput(ob_get_clean());
        return $actual;
    }
}

