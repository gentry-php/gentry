<?php

namespace Gentry\Gentry;

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
        foreach ($methods as $method => $calls) {
            if ($calls) {
                $this->addFeature($class, new ReflectionMethod($class->getName(), $method), $calls);
            }
        }
    }

    /**
     * Internal method to add a testable feature.
     *
     * @param string The reflection of the object or trait to test a feature on.
     * @param ReflectionMethod $method Reflection of the feature to test.
     * @param array $calls Array of possible types of calls.
     */
    private function addFeature(ReflectionClass $class, ReflectionMethod $method, array $calls)
    {
        if (VERBOSE) {
            out("<gray> Adding feature <magenta>{$class->name}::{$method->name}\n");
        }
        $md5 = md5(microtime());
        $tested = $method->name;
        $body = [];
        $docblock = [];
        if ($class->isTrait()) {
            $arguments[] = "\stdClass \$test = null";
            $body[] = <<<EOT
\$anon = new class () {
    use {$class->name};
};
\$test = \Gentry\Gentry\Test::createWrappedObject(new \ReflectionClass(\$anon), \$anon);
EOT;
        } elseif ($class->isFinal()) {
            $arguments[] = "{$class->name} \$test";
            $body[] = <<<EOT
\$test = \Gentry\Gentry\Test::createWrappedObject(new \ReflectionClass(\$test), \$test);
EOT;
        } else {
            $arguments[] = "{$class->name} \$test";
        }
        $fallback = cleanDocComment($method, false);
        foreach ($method->getParameters() as $param) {
            $arguments[] = new Argument($param, $fallback);
        }
        foreach ($calls as $call) {
            $docblock[] = "{$class->name}::{$tested}(".implode(', ', $call).") {?}";
            $arglist = [];
            foreach ($call as $idx => $p) {
                if ($p == 'NULL') {
                    $arglist[] = 'null';
                } elseif (isset($arguments[$idx + 1])) {
                    preg_match('@\$\w+@', "{$arguments[$idx + 1]}", $matches);
                    $arglist[] = $matches[0];
                } else {
                    $arglist[] = $this->getDefaultForType($p);
                }
            }
            $mt = $method->isStatic() ? '::' : '->';
            $body[] = "yield assert(\$test$mt$tested(".implode(', ', $arglist)."));";
        }
        $arguments = implode(', ', $arguments);
        $body = trim(implode("\n        ", $body));
        $docblock = trim(implode("\n     * ", $docblock));
        $this->features[] = <<<EOT
    /**
     * [GENERATED] {0}::$tested
     *
     * $docblock
     */
    public function gentry_$md5($arguments)
    {
        throw new \Gentry\Gentry\IncompleteTestException("{$class->name}", "{$tested}");
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

