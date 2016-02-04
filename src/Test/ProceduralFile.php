<?php

namespace Gentry\Test;

use Exception;
use ReflectionFunction;
use ReflectionException;
use Gentry\File;

class ProceduralFile extends Method
{
    private $file;

    public function __construct(array $description, File &$file)
    {
        $this->file = $file;
        parent::__construct(
            $description,
            'getReturnedValue',
            'Gentry\File',
            new ReflectionFunction(function () {})
        );
    }

    /**
     * Include a procedural file and return resulting object representation.
     *
     * @param array $args Arguments to test with.
     * @return Gentry\File An object representing the included file's
     *  environment.
     * @see Gentry\File
     */
    public function actual(array $args)
    {
        $actual = ['thrown' => null, 'out' => '', 'result' => false];
        $result = false;
        $out = '';
        $vars = [];
        try {
            foreach (call_user_func(function () {
                ob_start();
                yield 'result' => include $this->file->__toString();
                yield 'out' => \Gentry\cleanOutput(ob_get_clean());
                yield 'vars' => get_defined_vars();
            }) as $var => $val) {
                $$var = $val;
            }
        } catch (Exception $e) {
            $actual['thrown'] = $e;
            $actual['out'] = \Gentry\cleanOutput(ob_get_clean());
        }
        $this->file->setIncludeResults($result, $out, $vars);
        $args[$this->target] = $this->file;
        $actual['result']  = $result;
        return $actual + parent::actual($args);
    }

    public function testedFeature()
    {
        return $this->file->getBaseName();
    }
}

