<?php

namespace Gentry;

use Reflector;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionException;
use zpt\anno\Annotations;
use Exception;
use ErrorException;
use Closure;
use Generator;
use SplFileInfo;

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
            '@(^|[!\?,;\.]).*?{(\d+)}.*?(?=$|[!\?,;\.])@ms',
            $description,
            $sentences,
            PREG_SET_ORDER
        )) {
            $matches = [];
            foreach ($sentences as $sentence) {
                $work = $sentence[0];
                $cnt = preg_match_all(
                    '@(.*?){(\d+)}?@ms',
                    $work,
                    $in_sentence,
                    PREG_SET_ORDER
                );
                if ($cnt == 1) {
                    $matches[] = $sentence;
                } else {
                    foreach ($in_sentence as $sub) {
                        $matches[] = $sub;
                        $work = str_replace($sub[0], '', $work);
                    }
                    if (strlen($work)) {
                        $matches[count($matches) - 1][0] .= $work;
                    }
                }
            }
            $arguments = $this->getArguments();
            $this->features = $matches;
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
        foreach ($invoke() as $id => $result) {
            $out = cleanOutput(ob_get_clean());
            if ($result instanceof Exception) {
                $thrown = $result;
                $result = null;
            } else {
                $thrown = null;
            }
            $expect = compact('result', 'thrown', 'out');
            if ($feature = array_shift($this->features)) {
                if ($result instanceof Closure) {
                    if ($this->params[$feature[2]]->isCallable()) {
                        $feature = new Test\ProceduralFunction(
                            $feature,
                            $args[$feature[2]],
                            new ReflectionFunction($result)
                        );
                    } else {
                        $feature = new Test\Method(
                            $feature,
                            $id,
                            $this->params[$feature[2]]->getClass()->name,
                            new ReflectionFunction($result)
                        );
                    }
                    $result = call_user_func($result);
                } elseif (is_string($args[$feature[2]])) {
                    if ($id === 'execute') {
                        $feature = new Test\Executable(
                            $feature,
                            $args[$feature[2]]
                        );
                    }
                } elseif (is_integer($id)) {
                    if ($args[$feature[2]] instanceof SplFileInfo) {
                        $id = 'getReturnedValue';
                        $args[$feature[2]] = new File($args[$feature[2]]);
                        $feature = new Test\ProceduralFile(
                            $feature,
                            $args[$feature[2]]
                        );
                    }
                } else {
                    $feature = new Test\Property(
                        $feature,
                        $id,
                        $this->params[$feature[2]]->getClass()->name
                    );
                }
                foreach ($this->results($result, $expect) as $pipe => $exp) {
                    $assert = $feature->assert(
                        $args,
                        $exp,
                        $this->pipe($pipe, $exp['result'])
                    );
                }
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

    private function results($result, array $expect)
    {
        if (!($result instanceof Generator)) {
            $result = [null => $result];
        }
        ob_start();
        foreach ($result as $pipe => $res) {
            $expected = [
                'result' => $res,
                'thrown' => null,
                'out' => $expect['out'].\Gentry\cleanOutput(ob_get_clean()),
            ];
            if ($res instanceof Exception) {
                $expected['thrown'] = $res;
                $expected['result'] = null;
            }
            yield $pipe => $expected;
            ob_start();
        }
        ob_end_clean();
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

    /**
     * Helper method to (re)attach default pipes to a testclass on a per-feature
     * basis (since the actual `$result` isn't passed to the closures as an
     * argument). Usually called automatically.
     *
     * @param mixed $pipe A possibly callable pipe.
     * @param mixed $result Whatever result the test expects.
     */
    private function pipe($pipe, $result)
    {
        if (is_numeric($pipe)) {
            return null;
        }
        switch ($pipe) {
            case 'is_a':
            case 'is_subclass_of':
            case 'method_exists':
            case 'property_exists':
                return function ($res) use ($pipe, $result) {
                    return call_user_func($pipe, $res, $result);
                };
            case 'matches':
                return function ($res) use ($result) {
                    return (bool)preg_match($result, $res);
                };
            case 'count':
                return function ($res) use ($result) {
                    if ($res instanceof Generator) {
                        $i = 0;
                        foreach ($res as $item) {
                            $i++;
                        }
                        return $i;
                    } elseif (is_array($res)) {
                        return count($res);
                    }
                    return false;
                };
            default:
                if (is_callable($pipe)) {
                    return $pipe;
                }
                return null;
        }
    }
}

