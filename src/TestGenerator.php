<?php

namespace Gentry\Gentry;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use StdClass;
use Twig_Loader_Filesystem;
use Twig_Environment;

/**
 * A test generation object. Normally, this is automatically called when running
 * the Gentry executable.
 */
class TestGenerator
{
    private $config;
    private $name;
    private $features = [];

    /**
     * Constructor. Pass the configuration object.
     *
     * @param StdClass $config
     */
    public function __construct(StdClass $config)
    {
        $this->config = $config;
        $loader = new Twig_Loader_Filesystem($this->config->src);
        $this->twig = new Twig_Environment($loader, ['cache' => false]);
    }

    /**
     * Generates stub test methods for all found features.
     *
     * @param ReflectionClass $class The reflection of the object or trait under
     *  test.
     * @param array $methods Array of reflected methods to generate test
     *  skeletons for.
     * @param array $uncovered Array of uncovered method calls.
     */
    public function generate(ReflectionClass $class, array $methods, array $uncovered)
    {
        $this->objectUnderTest = $class;
        $this->features = [];
        foreach ($methods as $method) {
            if (isset($uncovered[$method->name]) && $uncovered[$method->name]) {
                $this->addFeature($class, $method, $uncovered[$method->name]);
            }
        }
        var_dump($this->render());
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
        out("<gray> Adding tests for feature <magenta>{$class->name}::{$method->name}\n");
        $tested = $method->name;
        $this->features[$method->name] = [];
        foreach ($calls as $call) {
            $arglist = [];
            foreach ($call as $idx => $p) {
                if ($p == 'NULL') {
                    $arglist[] = 'null';
                } elseif (isset($arguments[$idx + 1])) {
                    preg_match('@\$\w+@', "{$arguments[$idx + 1]}", $matches);
                    $arglist[] = $matches[0];
                } else {
                    //$arglist[] = $this->getDefaultForType($p);
                    $arglist[] = 'null';
                }
            }
            $mt = $method->isStatic() ? '::' : '->';
        }
        /*
        $body = [];
        $docblock = [];
        if ($class->isTrait()) {
            $arguments[] = "\stdClass \$test = null";
            $BODY[] = <<<EOT
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
    public function gentry_$md5($arguments)
    {
        throw new \Gentry\Gentry\IncompleteTestException("{$class->name}", "{$tested}");
        $body
    }
EOT;
     */
    }

    private function render()
    {
        return $this->twig->render('template.html.twig', [
            'namespace' => $this->config->namespace ?? null,
            'objectUnderTest' => $this->objectUnderTest->name,
            'features' => $this->features,
        ]);
    }

    /**
     * Actually write the generated stubs to a randomized file.
     */
    public function write()
    {
        die();
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

