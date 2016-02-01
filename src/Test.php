<?php

namespace Gentry;

use Reflector;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionException;
use zpt\anno\Annotations;
use Closure;
use Exception;
use ErrorException;
use Generator;

/**
 * The main test class. Normally gets constructed internally, you don't have
 * to make your tests extend anything.
 */
class Test
{
    private $test;
    private $target;
    private $params;
    private $annotations;
    private $description;
    private $feature;
    private $testtype = 'method';
    private $testedFeatures = [];
    private $features = [];

    /**
     * Constructor.
     *
     * @param mixed $target The test class this scenario is targeting.
     * @param Reflector $function Reflected function representing this
     *  particular scenario.
     */
    public function __construct($target, Reflector $function)
    {
        $this->test = $function;
        $this->target = $target;
        $this->params = $this->test->getParameters();
        $this->annotations = new Annotations($this->test);
        $description = cleanDocComment($this->test);
        $description = preg_replace("@\s{1,}@m", ' ', $description);
        if (preg_match_all(
            '@(^|[!\?,:;\.]).*?{(\d+)}(::\$?\w+)?.*?(?=$|[!\?,:;\.])@ms',
            $description,
            $matches,
            PREG_SET_ORDER
        )) {
            $arguments = $this->getArguments();
            foreach ($matches as $match) {
                $match[0] = preg_replace('@{(\d+)}::\$?\w+@m', '{\\1}', $match[0]);
                if (isset($match[3])) {
                    $prop = substr($match[3], 2);
                    if ($prop{0} == '$') {
                        $this->features[] = new Test\Property(
                            $match[0],
                            $match[2],
                            substr($prop, 1),
                            $this->params[$match[2]]->getClass()->name
                        );
                    } else {
                        $this->features[] = new Test\Method(
                            $match[0],
                            $match[2],
                            $prop,
                            $this->params[$match[2]]->getClass()->name
                        );
                    }
                } elseif (is_string($arguments[$match[2]])) {
                    $this->features[] = new Test\Executable(
                        $match[0],
                        $match[2],
                        $arguments[$match[2]]
                    );
                } elseif ($this->params[$match[2]]->isCallable()) {
                    $this->features[] = new Test\ProceduralFunction(
                        $match[0],
                        $match[2],
                        $arguments[$match[2]]
                    );
                } elseif ($class = $this->params[$match[2]]->getClass()) {
                    $this->features[] = new Test\Proxy(
                        $match[0],
                        $match[2],
                        $this->params[$match[2]]->getClass()->name
                    );
                }
            }
        }
        $this->description = $description;
    }

    /**
     * Runs this scenario. Normally called internally by the Gentry executable.
     *
     * @param int &$passed Global number of tests passed so far.
     * @param int &$failed Global number of tests failed so far.
     * @param array &$messages Array of messages so far (for verbose mode).
     * @return array An array of the arguments used when testing.
     */
    public function run(&$passed, &$failed, array &$messages)
    {
        out('  * ');
        $args = [];
        try {
            $args = $this->getArguments();
        } catch (Exception $e) {
            out("<blue>{$this->description}");
            out("<red>[FAILED]\n");
            $messages[] = "Exception thrown during construction of argument: <magenta>".get_class($e)." (".$e->getMessage().")";
            $failed++;
            return;
        }
        if (isset($this->annotations['Incomplete']) && \Gentry\VERBOSE) {
            out("<blue>{$this->description}");
            out("<magenta>[INCOMPLETE]\n");
            return;
        }
        $expected = [
            'result' => null,
            'thrown' => null,
            'out' => '',
        ];
        $invoke = function () use ($args) {
            return $runs = $this->test instanceof ReflectionMethod ?
                $this->test->invokeArgs($this->target, $args) :
                $runs = $this->test->invokeArgs($args);
        };
        ob_start();
        foreach ($invoke() as $pipe => $result) {
            $out = cleanOutput(ob_get_clean());
            if ($result instanceof Exception) {
                $thrown = $result;
                $result = null;
            } else {
                $thrown = null;
            }
            $expect = compact('result', 'thrown', 'out');
            Test\Feature::addPipes($this, $result);
            if ($feature = array_shift($this->features)) {
                if ($feature instanceof Test\Proxy) {
                    if (is_numeric($pipe)) {
                        out("<blue>$this->description}");
                        out("<red>[FAILED]\n");
                        $messages[] = "Pipe {$pipe} must be the name of a method when building integration tests.";
                        $failed++;
                        return;
                    }
                    if (!is_callable($result)) {
                        out("<blue>{$this->description}");
                        out("<red>[FAILED]\n");
                        $messages[] = "Yielded value for integration tests must be a callable injecting the proxied feature's arguments.";
                        $failed++;
                        return;
                    }
                    if (!$feature->setProxiedFeature(
                        $this->target,
                        $pipe,
                        new ReflectionFunction($result)
                    )) {
                        out("<blue>{$this->description}");
                        out("<red>[FAILED]\n");
                        $messages[] = "<magenta>{$feature->class}::{$pipe}<gray>: No such method.";
                        $failed++;
                        return;
                    }
                    $pipe = null;
                }
                if (!is_numeric($pipe)) {
                    if (isset($this->target->$pipe)
                        && is_callable($this->target->$pipe)
                    ) {
                        $pipe = $this->target->$pipe;
                    } elseif (!is_callable($pipe)) {
                        $pipe = null;
                    }
                    $expect['result'] = true;
                } else {
                    $pipe = null;
                }
                if (is_callable($result)) {
                    $pipe = $result;
                    $expect['result'] = true;
                }
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
                    out("<red>[FAILED]\n");
                    $messages = array_merge($messages, $feature->messages);
                    $failed++;
                    return;
                } else {
                    out("<green>[OK]");
                    $passed++;
                }
            } else {
                out("<magenta>[SKIPPED]");
            }
            ob_start();
        }
        ob_end_clean();
        out("\n");
        return $args;
    }

    /**
     * Return a hash of all features tested by this scenario. Keys are the
     * classnames of the tested objects, values an array of tested features
     * (in either `method` or `$property` format).
     *
     * @return array
     */
    public function getTestedFeatures()
    {
        return $this->testedFeatures;
    }

    /**
     * Return a prepared array of arguments to use.
     *
     * @return array
     */
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

    /**
     * Internal method to reset arguments to pristince state and get a testable
     * version of them (with classnames instead of null for static tests).
     *
     * @param array &$args Array of original arguments.
     * @return array Array of testable arguments.
     */
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

    /**
     * Resets all "superglobals" to empty arrays (except $GLOBAL itself). To be
     * called when needed from your `__wakeup` method.
     */
    public static function resetAllSuperglobals()
    {
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
        $_COOKIE = [];
    }
}

