<?php

namespace Gentry;

use Reflector;
use ReflectionMethod;
use ReflectionException;
use zpt\anno\Annotations;
use Closure;
use Exception;
use ErrorException;
use Generator;

class Test
{
    private $test;
    private $target;
    private $params;
    private $annotations;
    private $description;
    private $feature;
    private $inject;
    private $testtype = 'method';
    private $testedFeatures = [];
    private $features = [];

    public function __construct($target, Reflector $function, $inject = null)
    {
        $this->test = $function;
        $this->target = $target;
        $this->params = $this->test->getParameters();
        $this->inject = $inject;
        $this->annotations = new Annotations($this->test);
        $description = cleanDocComment($this->test);
        if (preg_match_all(
            '@\{(\d+)\}::(\$?\w+)?@',
            $description,
            $matches,
            PREG_SET_ORDER
        )) {
            $arguments = $this->getArguments();
            foreach ($matches as $match) {
                $prop = $match[2];
                if ($prop{0} == '$') {
                    $this->features[] = new Test\Property(
                        $match[1],
                        substr($prop, 1)
                    );
                } else {
                    $this->features[] = new Test\Method(
                        $match[1],
                        $prop,
                        $this->params[$match[1]]->getClass()->name
                    );
                }
                $this->feature = $prop;
            }
        }
        $this->description = $description;
    }

    public function run(&$passed, &$failed, array &$messages)
    {
        $args = [];
        try {
            $args = $this->getArguments();
        } catch (Exception $e) {
            out("  * <blue>{$this->description}");
            out(" <red>[FAILED]\n");
            $failed[] = "Exception thrown during construction of argument: <magenta>".get_class($e)." (".$e->getMessage().")";
            return;
        }
        $this->description = preg_replace_callback(
            '@{(\d+)}@m',
            function ($match) use ($args) {
                $work = $args[$match[1]];
                if (is_null($work)
                    and $class = $this->params[$match[1]]->getClass()
                ) {
                    $work = $class->name;
                } else {
                    $work = get_class($work);
                }
                return $work;
            },
            $this->description
        );
        if (isset($this->annotations['Incomplete']) && \Gentry\VERBOSE) {
            out("  * <blue>{$this->description}");
            out(" <magenta>[INCOMPLETE]\n");
            return;
        }
        if (isset($this->annotations['Repeat'])) {
            $iterations = $this->annotations['Repeat'];
        }
        $expected = [
            'result' => null,
            'thrown' => null,
            'out' => '',
        ];
        out("  * <blue>{$this->description}");
        ob_start();
        try {
            if ($this->test instanceof ReflectionMethod) {
                $runs = $this->test->invokeArgs($this->target, $args);
            } else {
                $runs = $this->test->invokeArgs($args);
            }
            $runs = $runs instanceof Generator ? $runs : [$runs];
            $i = 0;
            $expected['out'] .= cleanOutput(ob_get_clean());
            ob_start();
            foreach ($runs as $pipe => $run) {
                $expect = ['result' => $run] + $expected;
                foreach ([
                    'is_a',
                    'is_subclass_of',
                    'method_exists',
                    'property_exists',
                ] as $magic) {
                    $this->target->$magic = function ($result) use ($run, $magic) {
                        return $$magic($result, $run);
                    };
                    $expect['result'] = true;
                }
                if (!is_numeric($pipe)) {
                    if (isset($this->target->$pipe)
                        && is_callable($this->target->$pipe)
                    ) {
                        $pipe = $this->target->$pipe;
                    } elseif (!is_callable($pipe)) {
                        $pipe = null;
                    }
                } else {
                    $pipe = null;
                }
                $expected['out'] .= cleanOutput(ob_get_clean());
                $expect = ['result' => $run] + $expected;
                if (is_callable($run)) {
                    $pipe = $run;
                    $expect['result'] = true;
                }
                if ($feature = array_shift($this->features)) {
                    $assert = $feature->assert($args, $expect, $pipe);
                    $tested = $feature->tested;
                    if (!isset($this->testedFeatures[$tested])) {
                        $this->testedFeatures[$tested] = [];
                    }
                    $name = $feature->name;
                    if ($feature instanceof Test\Property) {
                        $name = "\$$name";
                    }
                    if (!in_array($name, $this->testedFeatures[$tested])) {
                        $this->testedFeatures[$tested][] = $name;
                    }
                    if (!$assert) {
                        out(" <red>[FAILED]\n");
                        $messages = array_merge($messages, $feature->messages);
                        $failed++;
                        return;
                    } else {
                        out(" <green>[OK]");
                        $passed++;
                    }
                } else {
                    out(" <magenta>[SKIPPED]");
                }
                ob_start();
            }
        } catch (Exception $e) {
            $expected['thrown'] = $e;
        }
        ob_end_clean();
        out("\n");
        return $args;
    }

    public function getTestedFeatures()
    {
        return $this->testedFeatures;
    }

    public function getArguments()
    {
        $args = [];
        foreach ($this->params as $i => $param) {
            call_user_func(function () use ($param, &$args) {
                if ($param->isDefaultValueAvailable()) {
                    $work = $param->getDefaultValue();
                } elseif ($type = $param->getClass()) {
                    $work = $type->newInstance();
                } else {
                    $work = null;
                }
                $args[] =& $work;
            });
        }
        return $args;
    }

    protected function resetArguments(&$args)
    {
        $testargs = $args;
        foreach ($args as $i => $arg) {
            if ($this->params[$i]->isDefaultValueAvailable()) {
                $args[$i] = $this->params[$i]->getDefaultValue();
            }
        }
        foreach ($testargs as $i => $arg) {
            if (is_null($arg) and $class = $this->params[$i]->getClass()) {
                $testargs[$i] = $class->name;
            }
        }
        return $testargs;
    }
}

