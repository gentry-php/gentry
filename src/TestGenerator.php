<?php

namespace Gentry;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

/**
 * A test generation object. Normally, this is automatically called when running
 * the Gentry executable with the `-g`[enerate] flag.
 */
class TestGenerator
{
    private $path;
    private $name;
    private $features = [];

    /**
     * Constructor. Pass the path to test files.
     *
     * @param string $path The full path to the test files.
     */
    public function __construct($path)
    {
        $this->path = $path;
        $this->name = "Gentry_".md5(time());
    }

    /**
     * Generates stub test methods for all found features.
     *
     * @param ReflectionClass $class The reflection of the object or trait under
     *  test.
     * @param array $methods Array of reflected methods to generate test
     *  skeletons for.
     */
    public function generate(ReflectionClass $class, array $methods)
    {
        foreach ($methods as $method) {
            $this->addFeature($class, $method);
        }
    }

    /**
     * Internal method to add a testable feature.
     *
     * @param string The reflection of the object or trait to test a feature on.
     * @param ReflectionMethod $method Reflection of the feature to test.
     */
    private function addFeature(ReflectionClass $class, ReflectionMethod $method)
    {
        if (VERBOSE) {
            out("<gray> Adding feature <magenta>{$class->name}::{$method->name}\n");
        }
        $md5 = md5(microtime());
        $tested = $method->name;
        $arguments[] = "{$class->name} \$test";
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
            $body = [];
            foreach ($types as $type) {
                if ($type == 'boolean') {
                    $type = 'bool';
                }
                if ($type == 'integer') {
                    $type = 'int';
                }
                switch ($type) {
                    case 'int':
                    case 'float':
                    case 'string':
                    case 'array':
                    case 'bool':
                    case 'callable':
                        $body[] = "yield 'is_$type' => true;";
                        break;
                    case 'mixed':
                        $body[] = "yield 'isset' => true;";
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

    /**
     * Actually write the generated stubs to a randomized file.
     */
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

