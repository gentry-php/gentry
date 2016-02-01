<?php

namespace Gentry\Test;

use Exception;
use ReflectionFunction;
use ReflectionException;

class ProceduralFile extends Feature
{
    private $fiile;

    public function __construct($description, $target, &$file)
    {
        parent::__construct($description, $target, '', '');
        $this->file = $file;
    }

    /**
     * Get a hash of actual function results.
     *
     * @param array $args Arguments to test with.
     * @return array A hash with value, thrown exception and output results.
     */
    public function actual(array &$args)
    {
        $this->file = $args[$this->target];
        $actual = ['thrown' => null, 'out' => '', 'result' => false];
        $resutl = false;
        try {
            $before = [];
            list($result, $vars) = call_user_func(function () {
                ob_start();
                return [include $this->file, get_defined_vars()];
            });
            $after = array_diff($before, $vars);
        } catch (Exception $e) {
            $actual['thrown'] = $e;
            $after = [];
        }
        $actual['result'] = $result;
        $actual['out'] = \Gentry\cleanOutput(ob_get_clean());
        return $actual;
    }

    public function testedFeature()
    {
        return $this->file;
    }
}

