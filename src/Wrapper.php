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

class Wrapper
{
    /**
     * Creates an anonymous object based on a reflection. The wrapped object
     * proxies public methods to the actual implementation, logs their
     * invocations and traps any exceptions.
     *
     * @param mixed $object A class, object or trait to wrap.
     * @param mixed ...$args Arguments for use during construction.
     * @return object An anonymous, wrapped object.
     */
    public static function createObject($class, ...$args)
    {
        $type = new ReflectionClass($class);
        // This is nasty, but we need to dynamically extend the
        // original class to allow type hinting to work.
        $mod = '{';
        $pclass = 'get_parent_class($this)';
        if ($type->isTrait()) {
            $mod = "{ use {$type->name};";
            $pclass = "{$type->name}::class";
        } elseif ($type->isInterface()) {
            $mod = "implements {$type->name} {";
            $pclass = "{$type->name}::class";
        } else {
            $mod = "extends {$type->name} {";
        }
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
        self::__gentryLogMethodCall('%2\$s', $pclass, \$args);
    }
    array_walk(\$args, function (\$arg) use (&\$refargs) {
        \$refargs[] = &\$arg;
    });
    return parent::%2\$s(...\$refargs);
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
        } catch (Throwable $e) {
            return $work;
        }
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
                    $args[] = self::createObject($class);
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
        $param = array_shift($params);
        $opts = [];
        if (!$param->hasType()) {
            $opts[] = 'mixed';
        } else {
            $opts[] = $param->getType()->__toString();
        }
        foreach (self::getPossibleCalls(...$params) as $sub) {
            $options[] = array_merge($opts, $sub);
        }
        if ($param->isOptional() && !$param->isVariadic()) {
            $opts[0] = getNormalisedType($param->getDefaultValue());
            foreach (self::getPossibleCalls(...$params) as $sub) {
                $options[] = array_merge($opts, $sub);
            }
        }
        return $options;
    }
}

