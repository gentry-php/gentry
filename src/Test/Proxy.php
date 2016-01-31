<?php

namespace Gentry\Test;

use Reflector;
use ReflectionMethod;

/**
 * A "proxy" feature that receives its method to test from the "pipe".
 */
class Proxy extends Method
{
    private $proxied;

    /**
     * Constructor. Note the `$name` of the feature is omitted for now, since
     * we won't know what it is until later, and the target is always 0.
     *
     * @param string $description Description of the scenario.
     * @param int $target Index of the target parameter. Note that internally
     *  this class always assumes 0, but the description might say otherwise.
     * @param string $class The target's actual classname, for static calls.
     */
    public function __construct($description, $target, $class)
    {
        parent::__construct($description, $target, null, $class);
    }

    /**
     * Register the proxied feature.
     *
     * @param object $testclass The proxied testclass.
     * @param string $name The feature's name. Must be a public method on the
     *  `$target` class.
     * @param Reflector $function Reflection of the specified callable defining
     *  the arguments to call the feature with.
     * @return bool True on success, false if no such method can be proxied.
     */
    public function setProxiedFeature($testclass, $name, Reflector $function)
    {
        if (!method_exists($this->class, $name)) {
            return false;
        }
        $test = new ReflectionMethod($this->class, $name);
        if (!$test->isPublic()) {
            return false;
        }
        $this->name = $name;
        $this->proxied = $function;
        $this->testclass = $testclass;
        return true;
    }

    /**
     * Custom proxied assertion. The arguments passed from the parent test are
     * discared for proxied tests; instead, arguments taken from the yielded
     * callable are used.
     *
     * Note that method signatures must match, hence dummy parameters are
     * specified.
     *
     * @return bool True if the assertion holds, else false.
     */
    public function assert(array &$a, $e, callable $p = null)
    {
        $testedfeature = sprintf(
            "<darkBlue>%s::%s<blue>",
            $this->class,
            $this->name
        );
        \Gentry\out(str_replace(
            '{'.$this->target.'}',
            $testedfeature,
            "<blue>{$this->description}"
        ));
        $this->description = null;
        $args = [$a[$this->target]];
        $this->target = 0;
        foreach ($this->proxied->getParameters() as $argument) {
            $work =& $args[];
            if ($argument->isDefaultValueAvailable()) {
                $work = $argument->getDefaultValue();
            } elseif ($argument->hasClass()) {
                $work = $argument->getClass()->newInstance();
            }
        }
        $invoke = function () use (&$args) {
            return $this->proxied->invokeArgs($args);
        };
        ob_start();
        foreach ($invoke() as $pipe => $result) {
            $out = \Gentry\cleanOutput(ob_get_clean());
            if ($result instanceof Exception) {
                $thrown = $result;
                $result = null;
            } else {
                $thrown = null;
            }
            if (!is_numeric($pipe)) {
                if (isset($this->testclass->$pipe)) {
                    $pipe = $this->testclass->$pipe;
                }
                if (!is_callable($pipe)) {
                    $pipe = null;
                } else {
                    $result = true;
                }
            } else {
                $pipe = null;
            }
            $expect = compact('result', 'thrown', 'out');
            if (!parent::assert($args, $expect, $pipe)) {
                return false;
            }
        }
        return true;
    }
}

