<?php

namespace Gentry\Gentry;

use ErrorException;
use Monomelodies\Reflex\ReflectionMethod;

class Logger
{
    /** @var array[] */
    private $logged = [];

    /**
     * The Logger is implemented as a singleton.
     *
     * @return Gentry\Gentry\Logger
     */
    public static function getInstance() : Logger
    {
        static $instance = new Logger;
        return $instance;
    }

    /**
     * Log a feature.
     *
     * @param object $object
     * @param string $method
     * @param array $args Arguments used as types, so Gentry can check the
     *  various types of calls (e.g. with/without optional arguments).
     * @return void
     */
    public function logFeature(object $object, string $method, array $args) : void
    {
        $class = get_class($object);
        $reflection = new ReflectionMethod($object, $method);
        $parameters = $reflection->getParameters();
        array_walk($args, function (&$arg, $i) use ($reflection, $parameters) {
            if (isset($parameters[$i])) {
                $arg = $parameters[$i]->getNormalisedType() ?? 'unknown';
            } else {
                $j = $i;
                $arg = 'unknown';
                while ($j > 0) {
                    if (isset($parameters[--$j])) {
                        $arg = $parameters[$j]->getNormalisedType($arg) ?? 'unknown';
                        break;
                    }
                }
            }
        });
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

    /**
     * Returns hash of logged features.
     *
     * @return array
     */
    public function getLoggedFeatures() : array
    {
        return $this->logged;
    }

    /**
     * On destruction, cleanup the "global" log.
     *
     * @return void
     */
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

