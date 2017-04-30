<?php

namespace Gentry\Gentry;

use ErrorException;

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
        if (!in_array($args, $this->logged[$class][$method])) {
            $this->logged[$class][$method][] = $args;
        }
    }

    public function getLoggedFeatures()
    {
        return $this->logged;
    }

    public function __destruct()
    {
        try {
            $shm_key = ftok(realpath(__DIR__.'/../bin').'/gentry', 't');
            $shm = shmop_open($shm_key, 'w', 0644, 1024 * 1024);
            shmop_write($shm, serialize($this->logged), 0);
            shmop_close($shm);
        } catch (ErrorException $e) {
        }
    }
}

