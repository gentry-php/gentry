<?php

namespace Gentry\Test;

use Exception;
use Reflector;
use ReflectionFunction;
use ReflectionException;

class ProceduralFunction extends Method
{
    private $fn;

    public function __construct(array $description, $name, Reflector &$args)
    {
        $this->fn = $name;
        parent::__construct($description, '', null, $args);
    }

    /**
     * Get a hash of actual function results.
     *
     * @param array $args Arguments to test with.
     * @return array A hash with value, thrown exception and output results.
     */
    public function actual(array $args)
    {
        $actual = ['thrown' => null, 'out' => '', 'result' => false];
        try {
            $function = new ReflectionFunction($this->fn);
        } catch (ReflectionException $e) {
            $actual['thrown'] = $e;
            return $actual;
        }
        ob_start();
        try {
            $actual['result'] = $function->invokeArgs($this->args);
        } catch (Exception $e) {
            $actual['thrown'] = $e;
        }
        $actual['out'] = \Gentry\cleanOutput(ob_get_clean());
        return $actual;
    }

    public function testedFeature()
    {
        return $this->fn;
    }
}

