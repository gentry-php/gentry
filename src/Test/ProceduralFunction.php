<?php

namespace Gentry\Test;

use Exception;
use ReflectionFunction;
use ReflectionException;

class ProceduralFunction extends Feature
{
    private $fn;

    public function __construct(array $description, &$fn)
    {
        $this->fn = $fn;
        parent::__construct($description, '');
    }

    /**
     * Get a hash of actual function results.
     *
     * @param array $args Arguments to test with.
     * @return array A hash with value, thrown exception and output results.
     */
    public function actual(array $args)
    {
        $callargs = $args;
        unset($callargs[$this->target]);
        $callargs = array_values($callargs);
        $actual = ['thrown' => null, 'out' => '', 'result' => false];
        try {
            $function = new ReflectionFunction($this->fn);
        } catch (ReflectionException $e) {
            $actual['thrown'] = $e;
            return $actual;
        }
        ob_start();
        try {
            $actual['result'] = $function->invokeArgs($callargs);
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

