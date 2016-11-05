<?php

namespace Gentry\Gentry;

class Logger
{
    const METHOD = 'method';
    const PROCEDURE = 'procedure';

    private $logged = [];

    public static function getInstance()
    {
        static $instance;
        if (!isset($instance)) {
            $instance = new static;
        }
        return $instance;
    }

    public function logFeature($class, $method, array $args)
    {
        if (!isset($this->logged[$class])) {
            $this->logged[$class] = [];
        }
        if (!isset($this->logged[$class][$method])) {
            $this->logged[$class][$method] = [];
        }
        $this->logged[$class][$method][] = $args;
    }

    public function getLoggedFeatures()
    {
        return $this->logged;
    }
}

