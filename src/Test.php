<?php

namespace Gentry\Gentry;

use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use ReflectionException;
use zpt\anno\Annotations;
use Throwable;
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
        } catch (Throwable $e) {
            out("<blue>{$this->description}");
            out("<red>[FAILED]\n");
            $messages[] = "Exception thrown during construction of argument: <magenta>".get_class($e)." (".$e->getMessage().")";
            $failed++;
            return;
        }
        if (isset($this->annotations['Incomplete']) && VERBOSE) {
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
        } catch (Throwable $e) {
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
                        if ($type->isFinal()) {
                            $work = $type->newInstanceArgs(self::getConstructorArguments($type));
                        } else {
                            $work = self::createWrappedObject($type);
                        }
                    }
                } elseif ($param->isCallable()) {
                    $work = function ($fn, ...$arguments) {
                        $logger = Logger::getInstance();
                        $reflection = new ReflectionFunction($fn);
                        $logger->logFeature(null, $fn, Test::getPossibleCalls(...$reflection->getParameters()));
                        return $reflection->invokeArgs($arguments);
                    };
                }
                $args[] =& $work;
            });
        }
        return $args;
    }

    public static function getConstructorArguments(ReflectionClass $type)
    {
        $args = [];
        if ($constructor = $type->getConstructor()) {
            $params = $constructor->getParameters();
            foreach ($params as $param) {
                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } elseif ($class = $param->getClass()) {
                    $args[] = self::createWrappedObject($class);
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
        return $args;
    }

    /**
     * Creates an anonymous object based on a reflection. The wrapped object
     * proxies public methods to the actual implementation, logs their
     * invocations and traps any exceptions.
     *
     * @param ReflectionClass $type A reflected class or object to wrap.
     * @param mixed $instance An actual prepopulated instance of the class. This
     *  is mainly used to wrap classes marked as "final".
     * @return object An anonymous, wrapped object.
     */
    public static function createWrappedObject(ReflectionClass $type, $instance = null)
    {
        // This is nasty, but we need to dynamically extend the
        // original class to allow type hinting to work.
        $args = self::getConstructorArguments($type);
        $methods = [];
        foreach ($type->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->name != $type->name) {
                continue;
            }
            if ($method->getFileName() != $type->getFileName()) {
                continue;
            }
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
                if ($param->isVariadic()) {
                    $argument = "...$argument";
                }
                if ($argtype = $param->getType()) {
                    $argument = "$argtype $argument";
                }
                if ($param->isDefaultValueAvailable()) {
                    $argument .= ' = '
                        .self::tostring($param->getDefaultValue());
                }
                $arguments["'a$i'"] = $argument;
            }
            $methods[] = sprintf(
                <<<EOT
public %1\$sfunction %2\$s(%3\$s) %4\$s{
    \$refargs = [];
    \$args = func_get_args();
    if (isset(\$this)) {
        self::__gentryLogMethodCall(
            '%2\$s',
            \$this ?
                (isset(\$this->__gentryInstance) ? get_class(\$this->__gentryInstance) : null) :
                (isset(self::\$__gentryStaticInstance) ? get_class(self::\$__gentryStaticInstance) : null),
            \$args
        );
    }
    array_walk(\$args, function (\$arg) use (&\$refargs) {
        \$refargs[] = &\$arg;
    });
    if (isset(\$this)) {
        if (isset(\$this->__gentryInstance)) {
            return \$this->__gentryInstance->%2\$s(...\$refargs);
        } else {
            return parent::%2\$s(...\$refargs);
        }
    } else {
        if (isset(self::\$__gentryStaticInstance)) {
            \$static = self::\$__gentryStaticInstance;
            return \$static::%2\$s(...\$refargs);
        } else {
            return parent::%2\$s(...\$refargs);
        }
    }
}

EOT
                ,
                $method->isStatic() ? 'static ' : '',
                $method->name,
                implode(', ', $arguments),
                $method->hasReturnType() ? ': '.$method->getReturnType() : ''
            );
        }
        $methods = implode("\n", $methods);
        $mod = '{';
        if ($type->isTrait()) {
            $mod = "{ use {$type->name};";
        } elseif ($type->isInterface()) {
            $mod = "implements {$type->name} {";
        } elseif (!$instance) {
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
            $work->__gentryConstruct($instance, ...$args);
            return $work;
        } catch (Throwable $e) {
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
    private static function tostring($value)
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
                $out .= $key.' => '.self::tostring($entry);
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

    /**
     * Return an array of all possible combinations of variable types.
     *
     * @param ReflectionParameter ...$params Zero or more reflection parameters.
     * @return array Array of array of possible combination of parameter types
     *  this method accepts.
     */
    public static function getPossibleCalls(ReflectionParameter ...$params) : array {
        if (!count($params)) {
            return [[]];
        }
        $options = [];
        $opts = [];
        foreach ($params as $param) {
            if (!$param->hasType()) {
                $opts[] = 'mixed';
            } else {
                $opts[] = $param->getType()->__toString();
            }
        }
        $options[] = $opts;
        $last = array_pop($params);
        if ($last->isOptional() && !$last->isVariadic()) {
            $options = array_merge($options, self::getPossibleCalls(...$params));
        }
        return $options;
    }
}

