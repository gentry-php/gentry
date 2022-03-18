<?php

namespace Gentry\Gentry;

use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use ReflectionException;
use Throwable;
use ErrorException;
use Closure;
use Generator;
use SplFileInfo;
use AssertionError;

class Wrapper
{
    /**
     * Wraps an anonymous object based on a reflection. The wrapped object
     * proxies public methods to the actual implementation and logs their
     * invocations.
     *
     * Note that the constructor is not called; hence you'll usually use the
     * `createObject` method instead of calling this directly.
     *
     * @param mixed $class A class, object or trait to wrap.
     * @return object An anonymous, wrapped object.
     */
    public static function wrapObject($class) : object
    {
        $type = new ReflectionClass($class);
        // This is nasty, but we need to dynamically extend the
        // original class to allow type hinting to work.
        $mod = '{';
        $pclass = 'get_parent_class($this)';
        if ($type->isTrait()) {
            $mod = "{ use {$type->name} {\n";
            $aliases = [];
            $i = 0;
            foreach ($type->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                ++$i;
                $aliases[$method->name] = "__gentryAlias$i";
                $mod .= "{$type->name}::{$method->name} as __gentryAlias$i;\n";
            }
            $mod .= "}\n";
            $pclass = "{$type->name}::class";
        } elseif ($type->isInterface()) {
            $mod = "implements {$type->name} {";
            $pclass = "{$type->name}::class";
        } else {
            $mod = "extends {$type->name} {";
        }
        $methods = [];
        foreach ($type->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->name != '__construct') {
                if ($method->getDeclaringClass()->name != $type->name) {
                    continue;
                }
                if ($method->getFileName() != $type->getFileName()) {
                    continue;
                }
                if ($method->isFinal()) {
                    continue;
                }
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
                    if ((float)phpversion() >= 7.4) {
                        $argtype = $argtype->getName();
                    }
                    $argument = "$argtype $argument";
                }
                if ($param->isDefaultValueAvailable()) {
                    $argument .= ' = '
                        .self::tostring($param->getDefaultValue());
                }
                $arguments["'a$i'"] = $argument;
            }
            if ($method->name == '__construct') {
                foreach ($arguments as &$argument) {
                    $argument = preg_replace('@ =.*?$@', '', $argument).' = null';
                }
                $methods[] = sprintf(
                    <<<EOT
public function __construct(%1\$s) {
}

EOT
                    ,
                    implode(',', $arguments)
                );
            } else {
                $returnType = $method->getReturnType();
                if (isset($returnType)) {
                    if ((float)phpversion() >= 7.4) {
                        $returnType = $returnType->getName();
                    } else {
                        $returnType = "$returnType";
                    }
                }
                $methods[] = sprintf(
                    <<<EOT
public %1\$sfunction %7\$s%2\$s(%3\$s) %4\$s{
    \$refargs = [];
    \$args = func_get_args();
    if (isset(\$this)) {
        self::__gentryLogMethodCall('%2\$s', $pclass, \$args);
    }
    array_walk(\$args, function (\$arg) use (&\$refargs) {
        \$refargs[] = &\$arg;
    });
    if (isset(\$this, \$this->__gentryInheritedObject)) {
        %6\$s\$this->__gentryInheritedObject->%2\$s(...\$refargs);
    } else {
        %6\$s%5\$s%2\$s(...\$refargs);
    }
}

EOT
                    ,
                    $method->isStatic() ? 'static ' : '',
                    $type->isTrait() ? $aliases[$method->name] : $method->name,
                    implode(', ', $arguments),
                    $method->hasReturnType() ? ':'.($method->getReturnType()->allowsNull() ? '?' : '')." $returnType " : '',
                    $type->isTrait() ? ($method->isStatic() ? 'self::' : '$this->') : 'parent::',
                    $method->hasReturnType() && $returnType == 'void' ? '' : 'return ',
                    $method->returnsReference() ? '&' : ''
                );
            }
        }
        $methods = implode("\n", $methods);
        $wrapper = is_object($class) ? 'InstanceWrapper' : 'ClassWrapper';
        $definition = <<<EOT
\$work = new class $mod
    use Gentry\Gentry\\$wrapper;

    $methods
};
EOT;
        eval($definition);
        return $work;
    }

    /**
     * Creates an anonymous object based on a reflection.
     *
     * @param mixed $class A class, object or trait to wrap.
     * @param mixed ...$args Arguments for use during construction.
     * @return object An anonymous, wrapped object.
     * @see Wrapper::wrapObject
     */
    public static function createObject($class, ...$args) : object
    {
        $work = self::wrapObject($class);
        $work->__gentryConstruct(...$args);
        return $work;
    }

    /**
     * "Extends" an existing object based on reflection. The resulting object is
     * wrapped (like with `createObject`), but all properties are preserved.
     *
     * @param object $object
     * @param mixed ...$args Arguments used during construction.
     * @return object
     * @see Wrapper::wrapObject
     */
    public static function extendObject(object $object, ...$args) : object
    {
        $new = self::wrapObject($object);
        $reflection = new ReflectionClass($object);
        foreach ($reflection->getProperties() as $prop) {
            $name = $prop->getName();
            try {
                $prop->setAccessible(true);
                ValueStore::set($prop);
            } catch (ReflectionException $e) {
            } catch (Throwable $e) {
            }
        }
        $new->__gentryConstructFromInheritedObject($object, ...$args);
        return $new;
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
    private static function tostring($value) : string
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
     *
     * @return void
     */
    public static function resetAllSuperglobals() : void
    {
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
        $_COOKIE = [];
    }
}

