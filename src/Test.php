<?php

namespace Gentry;

use Reflector;
use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
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
        \Gentry\out("<blue>".array_shift($this->features));
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
                out("<green>[OK]");
                $passed++;
                if ($feature = array_shift($this->features)) {
                    out("<blue>$feature");
                }
                ob_start();
            }
        } catch (AssertionError $e) {
            out("<red>[FAILED]");
            $failed++;
            $messages[] = $e->getMessage();
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
        $instance = isset($this->target->{$param->name})
            && $this->target->{$param->name} instanceof $type->name ?
            $this->target->{$param->name} :
            null;
        if (isset($instance)) {
            $type = new ReflectionClass($instance);
        }
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
                            $args[] = $this->createWrappedObject(
                                new ReflectionClass("$ptype")
                            );
                    }
                }
            }
        }
        if (!isset($instance) && count($params) == count($args)) {
            $instance = $type->newInstanceArgs($args);
        }
        $methods = [];
        foreach ($type->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isInternal()) {
                continue;
            }
            if ($method->name == '__construct') {
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
                $arguments["\$a$i"] = $argument;
            }
            $methods[] = sprintf(
                <<<EOT
public %1\$sfunction %2\$s(%3\$s) {
    self::logGentryMethodCall('%2\$s'); 
    try {
        %4\$s
    } catch (Throwable \$e) {
        return \$e;
    }
}

EOT
                ,
                $method->isStatic() ? 'static ' : '',
                $method->name,
                implode(', ', $arguments),
                sprintf(
                    $method->isStatic() ?
                        'return parent::%1$s(%2$s);' :
                        'return $this->gentryInstance->%1$s(%2$s);',
                    $method->name,
                    implode(', ', array_keys($arguments))
                )
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
        $work = eval(<<<EOT
return new class $mod
    use Gentry\ClassWrapper;

    private \$gentryInstance;

    public function __construct() {
    }

    $methods
};
EOT
        );
        if (isset($instance)) {
            $work->setGentryInstance($instance);
        }
        return $work;
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
        if (is_scalar($value)) {
            return $value;
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

