<?php

namespace Gentry;

use Reflector;
use ReflectionMethod;
use zpt\anno\Annotations;
use Closure;
use Exception;

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

    public function __construct($target, Reflector $function, $inject = null)
    {
        $this->test = $function;
        $this->target = $target;
        $this->params = $this->test->getParameters();
        $this->inject = $inject;
        $this->annotations = new Annotations($this->test);
        if (isset($this->annotations['Scenario'])) {
            $description = $this->annotations['Scenario'];
            if (preg_match('@\{0\}(::\$?\w+)?@', $description, $matches)) {
                $prop = substr($matches[1], 2);
                if ($prop{0} == '$') {
                    $this->testtype = 'property';
                }
                $this->feature = $prop;
                $description = str_replace(
                    '{0}',
                    isset($inject) ?
                        get_class($inject) :
                        $this->params[0]->getClass()->name,
                    $description
                );
            }
        } else {
            $description = null;
        }
        $this->description = $description;
    }

    public function run(&$passed, array &$failed)
    {
        if (!isset($this->feature)) {
            out("<magenta>Warning: <gray>missing <magenta>@Scenario <gray>annotation with {0}::something declaration. Not sure what to do...\n", STDERR);
            $failed[] = sprintf(
                "<gray>Couldn't determine <magenta>feature<gray> for <magenta>%s<gray>, missing or invalid <magenta>@Scenario",
                get_class($this->target)
            );
            return;
        }
        $expected = $actual = [
            'result' => null,
            'thrown' => null,
            'out' => '',
        ];
        if (!isset($this->annotations['Incomplete']) || $verbose
            and isset($this->description)
        ) {
            out("  * <blue>{$this->description}");
        }
        if (isset($this->annotations['Incomplete'])) {
            if (VERBOSE) {
                out(" <magenta>[INCOMPLETE]\n");
            }
            return;
        }
        $iterations = 1;
        if (isset($this->annotations['Repeat'])) {
            $iterations = $this->annotations['Repeat'];
        }
        ob_start();
        try {
            $args = $this->getArguments();
            if ($this->test instanceof ReflectionMethod) {
                $expected['result'] = $this->test->invokeArgs($this->target, $args);
            } else {
                $expected['result'] = $this->test->invokeArgs($args);
            }
            if ($expected['result'] instanceof Group) {
                $expected['result']->run($passed, $failed);
                return;
            }
        } catch (Exception $e) {
            $expected['thrown'] = get_class($e);
        }
        $expected['out'] = ob_get_clean();
        if (isset($this->inject)) {
            array_unshift($args, $this->inject);
        }
        for ($i = 0; $i < $iterations; $i++) {
            ob_start();
            if (method_exists($args[0], $this->feature)) {
                try {
                    $actual['result'] = call_user_func_array(
                        [$args[0], $this->feature],
                        array_slice($args, 1)
                    );
                } catch (Exception $e) {
                    $actual['thrown'] = get_class($e);
                }
            } else {
                $property = substr($this->feature, 1);
                if (property_exists($args[0], $property)) {
                    $actual['result'] = $args[0]->$property;
                }
            }
            $actual['out'] .= ob_get_clean();
            if ($iterations > 1) {
                out('<blue>.');  
            }
        }
        if (!isset($this->annotations['Raw'])) {
            $expected['out'] = trim($expected['out']);
            $actual['out'] = trim($actual['out']);
        }
        if (is_object($expected['result'])) {
            if ($expected['result'] instanceof Closure) {
                $fn = $expected['result'];
                $expected['result'] = true;
                $actual['result'] = call_user_func($fn, $actual['result']);
            }
        }
        if (isset($this->annotations['Pipe'])) {
            $pipes = preg_split("@,\s+@", $this->annotations['Pipe']);
            while ($pipe = array_shift($pipes)) {
                $actual['result'] = $pipe($actual['result']);
            }
        }
        if (isEqual($expected['result'], $actual['result'])
            && $expected['thrown'] == $actual['thrown']
            && $expected['out'] == $actual['out']
        ) {
            $passed++;
            out(" <green>[OK]\n");
        } else {
            out(" <red>[FAILED]<reset> ");
            $testedfeature = sprintf(
                "<magenta>%s::%s:%s<gray>",
                get_class($this->target),
                $this->testtype == 'property' ? '$' : '',
                $this->feature
            );
            if (!isEqual($expected['result'], $actual['result'])) {
                $failed[] = sprintf(
                    "<gray>Expected %s to %s <magenta>%s<gray>, got <magenta>%s",
                    $testedfeature,
                    $this->testtype == 'property' ? 'contain' : 'return',
                    tostring($expected['result']),
                    tostring($actual['result'])
                );
            }
            if ($expected['thrown'] != $actual['thrown']) {
                $failed[] = sprintf(
                    "<gray>Expected %s to throw <magenta>{$expected['thrown']}<gray>, caught <meganta>%s",
                    $testedfeature,
                    $actual['thrown']
                );
            }
            if ($expected['out'] != $actual['out']) {
                $diff = strdiff($expected['out'], $actual['out']);
                $failed[] = sprintf(
                    "<gray>Expected %s to output:\n\"%\"<reset><gray>Actual output:\n\"%s\"<gray>",
                    $testedfeature,
                    $diff['old'],
                    $diff['new']
                );
            }
        }
        return $args;
    }

    public function getFeature()
    {
        return $this->feature;
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

