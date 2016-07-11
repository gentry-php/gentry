<?php

namespace Gentry\Gentry;

use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use ReflectionException;
use zpt\anno\Annotations;
use Exception;
use ErrorException;
use Closure;
use Generator;
use SplFileInfo;
use AssertionError;

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
    private $finally = '';
    private $feature;
    private $testtype = 'method';
    private $testedFeatures = [];
    private $features = [];

    /**
     * Constructor.
     *
     * @param mixed $target The test class this scenario is targeting.
     * @param ReflectionFunctionAbstract $function Reflected function
     *  representing this particular scenario.
     */
    public function __construct($target, ReflectionFunctionAbstract $function)
    {
        $this->test = $function;
        $this->target = $target;
        $this->params = $this->test->getParameters();
        $this->annotations = new Annotations($this->test);
        $description = cleanDocComment($this->test);
        $description = preg_replace("@\s{1,}@m", ' ', $description);
        $this->features = preg_split(
            '@{(\?)}@',
            $description
        );
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
        out("  * <blue>".array_shift($this->features));
        ob_start();
        try {
           foreach ($invoke() as $result) {
                $out = cleanOutput(ob_get_clean());
                if ($result instanceof Exception) {
                    $thrown = $result;
                    $result = null;
                } else {
                    $thrown = null;
                }
                if (strlen($out)) {
                    out("<darkGreen>[OK, but unchecked output]");
                } else {
                    out("<green>[OK]");
                }
                $passed++;
                if ($feature = array_shift($this->features)) {
                    out("<blue>$feature");
                }
                ob_start();
            }
        } catch (AssertionError $e) {
            out("<red>[FAILED]");
            $failed++;
            $messages[] = sprintf(
                '<darkGray>%s <gray>in <darkGray>%s <gray>on line <darkGray>%s',
                substr($e->getMessage(), 7, -1),
                basename($e->getFile()),
                $e->getLine()
            );
        } catch (Exception $e) {
            out("<red>[ERROR]");
            $failed++;
            $messages[] = sprintf(
                '<gray>Caught exception <darkGray>%s <gray> with message <darkGray>%s <gray>in <darkGray>%s <gray>on line <darkGray>%s',
                get_class($e),
                $e->getMessage(),
                basename($e->getFile()),
                $e->getLine()
            );
        }
        ob_end_clean();
        out("<blue>{$this->finally}\n");
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
                'out' => $expect['out'].cleanOutput(ob_get_clean()),
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
                $target = $this->target;
                if ($type = $param->getClass()) {
                    if ($type->isInternal()) {
                        $work = $param->isDefaultValueAvailable() ?
                            $param->getDefaultValue() :
                            $type->newInstance();
                    } else {
                        $work = $this->createWrappedObject($param);
                    }
                } elseif ($param->isCallable()) {
                    $work = function ($fn) {
                        $logger = Logger::getInstance();
                        $logger->logFeature(Logger::PROCEDURE, $fn);
                        $arguments = func_get_args();
                        array_shift($arguments);
                        $reflection = new ReflectionFunction($fn);
                        try {
                            return $reflection->invokeArgs($arguments);
                        } catch (Exception $e) {
                            return $e;
                        }
                    };
                }
                $args[] =& $work;
            });
        }
        return $args;
    }

    /**
     * Creates an anonymous object based on a reflection. The wrapped object
     * proxies public methods to the actual implementation, logs their
     * invocations and traps any exceptions.
     *
     * @param ReflectionParameter $param The parameter to reflect from.
     * @return object An anonymous, wrapped object.
     */
    private function createWrappedObject(ReflectionParameter $param)
    {
        $type = $param->getClass();
        // This is nasty, but we need to dynamically extend the
        // original class to allow type hinting to work.
        $args = [];
        $params = [];
        if ($constructor = $type->getConstructor()) {
            $params = $constructor->getParameters();
            foreach ($params as $param) {
                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } elseif ($class = $param->getClass()) {
                    $args[] = $this->createWrappedObject($param);
                } elseif ($ptype = $param->getType()) {
                    switch ("$ptype") {
                        case 'array':
                            $args[] = [];
                            break;
                        case 'string':
                            $args[] = '';
                            break;
                        case 'int':
                        case 'float':
                            $args[] = 0;
                            break;
                        case 'callable':
                            $args[] = function () {};
                            break;
                        default:
                            $args[] = null;
                    }
                }
            }
        }
        $methods = [];
        foreach ($type->getMethods() as $method) {
            if ($method->name == '__construct') {
                continue;
            }
            if ($method->isFinal()) {
                continue;
            }
            $arguments = [];
            foreach ($method->getParameters() as $i => $param) {
                $argument = "\$a$i";
                if ($param->isPassedByReference()) {
                    $argument = "&$argument";
                }
                if ($argtype = $param->getType()) {
                    $argument = "$argtype $argument";
                }
                if ($param->isDefaultValueAvailable()) {
                    $argument .= ' = '
                        .$this->tostring($param->getDefaultValue());
                }
                $arguments["'a$i'"] = $argument;
            }
            $methods[] = sprintf(
                <<<EOT
public %1\$sfunction %2\$s(%3\$s%5\$s...\$args) {
    self::__gentryLogMethodCall('%2\$s');
    \$args = array_merge(compact(%4\$s), \$args);
    return call_user_func_array('parent::%2\$s', \$args);
}

EOT
                ,
                $method->isStatic() ? 'static ' : '',
                $method->name,
                implode(', ', $arguments),
                implode(', ', array_keys($arguments)),
                $arguments ? ', ' : ''
            );
        }
        $methods = implode("\n", $methods);
        $mod = '{';
        if ($type->isTrait()) {
            $mod = "{ use {$type->name};";
        } elseif ($type->isInterface()) {
            $mod = "implements {$type->name} {";
        } else {
            $mod = "extends {$type->name} {";
        }
        $definition = <<<EOT
\$work = new class $mod
    use Gentry\Gentry\ClassWrapper;

    $methods
};
EOT;
        eval($definition);
        try {
            $work->__gentryConstruct(...$args);
            return $work;
        } catch (Exception $e) {
            return $work;
        }
    }

    /**
     * Internal helper method to get an echo'able representation of a random
     * value for reporting and code generation.
     *
     * @param mixed $value
     * @return string
     */
    private function tostring($value)
    {
        if (!isset($value)) {
            return 'NULL';
        }
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }
        if (is_numeric($value)) {
            return $value;
        }
        if (is_string($value)) {
            return "'$value'";
        }
        if (is_array($value)) {
            $out = '[';
            $i = 0;
            foreach ($value as $key => $entry) {
                if ($i) {
                    $out .= ', ';
                }
                $out .= $key.' => '.$this->tostring($entry);
                $i++;
            }
            $out .= ']';
            return $out;
        }
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return "$value";
            } else {
                return get_class($value);
            }
        }
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

