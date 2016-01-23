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
    private $methodname;
    private $inject;

    public function __construct($target, Reflector $function, $inject = null)
    {
        $this->test = $function;
        $this->target = $target;
        $this->params = $this->test->getParameters();
        $this->inject = $inject;
        $this->annotations = new Annotations($this->test);
        $this->methodname = isset($this->annotations['Test']) ?
            $this->annotations['Test'] :
            $this->test->name;
        if (isset($this->annotations['Scenario'])) {
            $description = $this->annotations['Scenario'];
            if (preg_match('@\{0\}(::\$?\w+)?@', $description, $matches)) {
                $prop = substr($matches[1], 2);
                if ($prop{0} == '$') {
                    $prop = substr($prop, 1);
                }
                $this->methodname = $prop;
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

    public function run(&$passed, &$failed)
    {
        $expected = $actual = ['result' => null, 'thrown' => null];
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
        if (isset($this->inject)) {
            array_unshift($args, $this->inject);
        }
        for ($i = 0; $i < $iterations; $i++) {
            if (method_exists($args[0], $this->methodname)) {
                try {
                    $actual['result'] = call_user_func_array(
                        [$args[0], $this->methodname],
                        $this->getRemoteArguments()
                    );
                } catch (Exception $e) {
                    $actual['thrown'] = get_class($e);
                }
            } else {
                if (property_exists($args[0], $this->methodname)) {
                    $actual['result'] = $args[0]->{$this->methodname};
                }
            }
            if ($iterations > 1) {
                out('<blue>.');  
            }
        }
        if (is_object($expected['result'])) {
            if ($expected['result'] instanceof Closure) {
                $fn = $expected['result'];
                $expected['result'] = true;
                $actual['result'] = call_user_func($fn, $actual['result']);
            }
        }
        if (isset($this->annotations['Pipe'])) {
            $actual['result'] = call_user_func($this->annotations['Pipe'], $actual['result']);
        }
        if (isEqual($expected['result'], $actual['result']) && $expected['thrown'] == $actual['thrown']) {
            $passed++;
            out(" <green>[OK]\n");
            return true;
        } else {
            out(" <red>[FAILED]<reset> ");
            $failed++;
            if (!isEqual($expected['result'], $actual['result'])) {
                out(sprintf(
                    "(expected <magenta>%s<reset>, got <magenta>%s<reset>)\n",
                    tostring($expected['result']),
                    tostring($actual['result'])
                ));
            } else {
                out(sprintf(
                    "(wanted to catch {$expected['thrown']}, got %s)\n",
                    isset($expected['result']) ? tostring($expected['result']) : $actual['thrown']
                ));
            }
        }
    }

    public function getMethodName()
    {
        return $this->methodname;
    }

    public function getArguments()
    {
        $args = [];
        foreach ($this->params as $i => $param) {
            if ($i == 0) {
                $type = $param->getClass();
                $classtype = $type->getName();
                if (!$param->isDefaultValueAvailable()
                    && isset($this->target->{$param->name})
                    && $this->target->{$param->name} instanceof $classtype
                ) { 
                    $args[] =& $this->target->{$param->name};
                } else {
                    $args[] = $type->newInstance();
                }
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif (isset($class->{$param->name})) {
                $args[] =& $class->{$param->name};
            } elseif ($type = $param->getClass()) {
                $args[] = $type->newInstance();
            } else {
                $args[] = null;
            }
        }
        return $args;
    }

    public function getRemoteArguments()
    {
        $remote = [];
        foreach ($this->params as $i => $param) {
            if ($i == 0) {
                continue;
            } elseif ($param->isDefaultValueAvailable()) {
                $remote[] = $param->getDefaultValue();
            } elseif (isset($class->{$param->name})) {
                $remote[] =& $class->{$param->name};
            } elseif ($type = $param->getClass()) {
                $remote[] = $type->newInstance();
            } else {
                $remote[] = null;
            }
        }
        return $remote;
    }
}

