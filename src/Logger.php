<?php

namespace Gentry\Gentry;

use ErrorException;
use Monomelodies\Reflex\ReflectionMethod;
use Shmop;
use Monomelodies\Kingconf;

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
     * On destruction, write logged features to a tmpfile.
     *
     * @return void
     */
    public function __destruct()
    {
        $config = self::getConfig();
        file_put_contents($config->tmpfile ?? sys_get_temp_dir().'/gentry', serialize($this->logged));
    }

    /**
     * Read data from the tmpfile.
     *
     * @return array
     */
    public static function read() : array
    {
        $config = self::getConfig();
        return unserialize(file_get_contents($config->tmpfile ?? sys_get_temp_dir().'/gentry'));
    }

    private static function getConfig() : object
    {
        $config = 'Gentry.json';
        try {
            return (object)(array)(new Kingconf\Config($config));
        } catch (Kingconf\Exception $e) {
            Formatter::out("<red>Error: <reset> Config file $config not found or invalid.\n", STDERR);
            die(1);
        }
    }
}

