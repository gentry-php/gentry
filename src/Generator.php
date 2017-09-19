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
class Generator
{
    private $config;
    private $objectUnserTest;
    private $features = [];

    /**
     * Constructor. Pass the configuration object.
     *
     * @param StdClass $config
     * @return void
     */
    public function __construct(StdClass $config)
    {
        $this->config = $config;
        $loader = new Twig_Loader_Filesystem($this->config->path);
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
     * @return void
     */
    public function generate(ReflectionClass $class, array $methods, array $uncovered) : void
    {
        $this->objectUnderTest = $class;
        $this->features = [];
        foreach ($methods as $method) {
            if (isset($uncovered[$method->name]) && $uncovered[$method->name]) {
                $this->addFeature($class, $method, $uncovered[$method->name]);
            }
        }
        $this->write();
    }

    /**
     * Internal method to add a testable feature.
     *
     * @param string The reflection of the object or trait to test a feature on.
     * @param ReflectionMethod $method Reflection of the feature to test.
     * @param array $calls Array of possible types of calls.
     * @return void
     */
    private function addFeature(ReflectionClass $class, ReflectionMethod $method, array $calls) : void
    {
        out("<gray> Adding tests for feature <magenta>{$class->name}::{$method->name}\n");
        $tested = $method->name;
        $this->features[$method->name] = (object)['calls' => []];
        foreach ($calls as $call) {
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
            $expectedResult = 'true';
            if ($method->hasReturnType()) {
                $type = $method->getReturnType()->__toString();
                $expectedResult = $this->getDefaultForType($type);
            }
            $this->features[$method->name]->calls[] = (object)[
                'name' => $method->name,
                'parameters' => implode(', ', $arglist),
                'expectedResult' => $expectedResult,
            ];
        }
    }

    /**
     * Actually write the generated stubs to file. If a file by the name of the
     * feature already exists, a number is appended.
     *
     * @return void
     */
    public function write() : void
    {
        if (!$this->features) {
            return;
        }
        $i = 0;
        while (true) {
            $file = sprintf(
                '%s/%s%s.php',
                $this->config->output,
                $this->normalize($this->objectUnderTest->getName()),
                $i ? ".$i" : ''
            );
            if (!file_exists($file)) {
                break;
            }
            ++$i;
        }
        file_put_contents($file, $this->render());
    }

    /**
     * Renders the testing code according to the supplied template.
     *
     * @return string
     */
    private function render() : string
    {
        return $this->twig->render('template.html.twig', [
            'namespace' => $this->config->namespace ?? null,
            'objectUnderTest' => $this->objectUnderTest->name,
            'features' => $this->features,
        ]);
    }

    /**
     * Get a string representation of a default value for a given type.
     *
     * @param string $type
     * @return string
     */
    private function getDefaultForType(string $type) : string
    {
        switch ($type) {
            case 'string': return "'blarps'";
            case 'int': return '1';
            case 'float': return '1.1';
            case 'mixed': return "'MIXED'";
            case 'callable': return 'function () {}';
            case 'bool': return 'true';
        }
        if (class_exists($type)) {
            return "$type::class";
        }
        return $type;
    }

    /**
     * Normalize the given string for use in the filesystem.
     *
     * @param string $name An object's name.
     * @return string
     */
    private function normalize(string $name) : string
    {
        return strtolower(str_replace('\\', '_', $name));
    }
}

