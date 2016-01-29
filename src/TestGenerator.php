<?php

namespace Gentry;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

class TestGenerator
{
    private $path;
    private $name;
    private $features = [];

    public function __construct($path)
    {
        $this->path = $path;
        $this->name = "Gentry_".md5(time());
    }

    /**
     * @param string $class The class name of the object under test.
     * @param array $methods Array of reflected methods to generate test
     *  skeletons for.
     */
    public function generate($class, array $methods)
    {
        foreach ($methods as $method) {
            $this->addFeature($class, $method);
        }
    }

    private function addFeature($class, ReflectionMethod $method)
    {
        if (VERBOSE) {
            out("<gray> Adding feature <magenta>$class::{$method->name}\n");
        }
        $md5 = md5(microtime());
        $tested = $method->name;
        $arguments[] = "$class \$test";
        $fallback = cleanDocComment($method, false);
        foreach ($method->getParameters() as $param) {
            $arguments[] = new Argument($param, $fallback);
        }
        $arguments = implode(', ', $arguments);
        $body = "throw new \Exception(\"Incomplete test!\");";
        if (version_compare(phpversion(), '7.0', '>=')
            and $type = $method->getReturnType()
        ) {
            $types = [$type->__toString()];
        } elseif (preg_match(
            "#@return\s+(.*?)\s+\.*?#ms",
            $fallback,
            $matches
        )) {
            $types = explode('|', $matches[1]);
        }
        if (isset($types) && $types) {
            $kw = count($types) > 1 ? 'yield' : 'return';
            $body = [];
            foreach ($types as $type) {
                switch ($type) {
                    case 'int':
                    case 'float':
                        $body[] = "$kw 0;";
                        break;
                    case 'string':
                        $body[] = "$kw '';";
                        break;
                    case 'array':
                        $body[] = "$kw [];";
                        break;
                    case 'bool':
                        $body[] = "$kw true";
                        break;
                    case 'callable':
                        $body[] = "yield 'is_callable' => true;";
                        break;
                    default:
                        $body[] = "yield 'is_a' => '$type';";
                }
            }
            $body = trim(implode("\n        ", $body));
        }
        $this->features[] = <<<EOT
    /**
     * [GENERATED] {0}::$tested
     *
     * @Incomplete
     */
    public function gentry_$md5($arguments)
    {
        $body
    }
EOT;
    }

    public function write()
    {
        $file = "{$this->path}/{$this->name}.php";
        $code = <<<EOT
<?php

class %s
{
%s
}


EOT;
        $code = sprintf($code, $this->name, implode("\n\n", $this->features));
        file_put_contents($file, $code);
    }
}

