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
                        $prop
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
            'pipe' => [],
        ];
        ob_start();
        try {
            if ($this->test instanceof ReflectionMethod) {
                $runs = $this->test->invokeArgs($this->target, $args);
            } else {
                $runs = $this->test->invokeArgs($args);
            }
            $i = 0;
            foreach ($args as $i => &$arg) {
                if (is_null($arg) and $class = $this->params[$i]->getClass()) {
                    $arg = $class->name;
                }
            }
        } catch (Exception $e) {
            $expected['thrown'] = $e;
        }
        $expected['out'] = cleanOutput(ob_get_clean());
        $runs = $runs instanceof Generator ? $runs : [$runs];
        out("  * <blue>{$this->description}");
        if (is_callable(end($runs))) {
            $runs[] = true;
        }
        foreach ($runs as $i => $run) {
            if (!isset($expect)) {
                $expect = $expected;
            }
            $expect = ['result' => $run] + $expect;
            if (is_callable($run)) {
                $expect['pipe'][] = $run;
                continue;
            }
            if ($feature = array_shift($this->features)) {
                $assert = $feature->assert($args, $expect);
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
                unset($expect);
                if (!$assert) {
                    out(" <red>[FAILED]\n");
                    $messages = array_merge($messages, $feature->messages);
                    $failed++;
                    return;
                } else {
                    out(" <green>[OK]");
                    $passed++;
                }
            }
        }
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
}

