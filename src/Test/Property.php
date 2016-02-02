<?php

namespace Gentry\Test;

use ErrorException;
use Exception;

/**
 * Test a property.
 */
class Property extends Feature
{
    /**
     * Get a hash of actual property value results.
     *
     * @param array $args Arguments to test with.
     * @return array A hash with value, exception and output results (if the
     *  property is virtual, these might apply!).
     */
    public function actual(array $args)
    {
        $actual = ['thrown' => null, 'out' => null];
        ob_start();
        $target = is_null($args[$this->target]) ?
            $this->class :
            $args[$this->target];
        $property = $this->name;
        try {
            $actual['result'] = is_object($target) ?
                $target->$property :
                $target::$$property;
        } catch (ErrorException $e) {
            $this->messages[] = sprintf(
                "<red>ERROR: <gray>No such property <magenta>%s::$%s",
                is_string($target) ? $target : get_class($target),
                $this->name
            );
        } catch (Exception $e) {
            $actual['thrown'] = $e;
        }
        $actual['out'] = \Gentry\cleanOutput(ob_get_clean());
        return $actual;
    }
}

