<?php

namespace Gentry\Gentry;

use ErrorException;
use Monomelodies\Reflex\ReflectionMethod;
use Shmop;

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
     * @param object|string $object
     * @param string $method
     * @param array $args Arguments used as types, so Gentry can check the
     *  various types of calls (e.g. with/without optional arguments).
     * @return void
     */
    public function logFeature(object|string $object, string $method, array $args) : void
    {
        $class = is_object($object) ? get_class($object) : $object;
        $reflection = new ReflectionMethod($class, $method);
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
     * On destruction, write logged features to a shared memory block.
     *
     * @return void
     */
    public function __destruct()
    {
        self::write(serialize($this->logged));
    }

    /**
     * Read data from shared memory.
     *
     * @return string
     */
    public static function read() : string
    {
        $shm = self::getShmHandle();
        $data = shmop_read($shm, 0, 1024 * 1024);
        shmop_delete($shm);
        return $data;
    }

    /**
     * Write data to shared memory.
     *
     * @param string $data
     * @return void
     */
    private static function write(string $data) : void
    {
        $shm = self::getShmHandle();
        shmop_write($shm, $data, 0);
        shmop_close($shm);
    }

    /**
     * Get a handle to the shared memory block.
     *
     * @return Shmop
     * @throws Gentry\Gentry\ErrorGettingSharedMemoryBlockException
     */
    private static function getShmHandle() : Shmop
    {
        $shm_key = ftok(realpath(__FILE__), 't');
        $shm = shmop_open($shm_key, 'w', 0644, 1024 * 1024);
        if ($shm) {
            return $shm;
        } else {
            throw new ErrorGettingSharedMemoryBlockException;
        }
    }
}

