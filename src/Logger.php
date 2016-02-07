<?php

namespace Gentry;

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

    public function logFeature($type, $logged)
    {
        if (!isset($this->logged[$type])) {
            $this->logged[$type] = [];
        }
        switch ($type) {
            case self::METHOD:
                $class = $logged[0];
                if (!isset($this->logged[$type][$class])) {
                    $this->logged[$type][$class] = [];
                }
                $this->logged[$type][$class][$logged[1]] = $logged[1];
                break;
        }
    }

    public function getLoggedFeatures()
    {
        return $this->logged;
    }
}

