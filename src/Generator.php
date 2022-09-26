<?php

namespace Gentry\Gentry;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use stdClass;
use Twig\{ Loader\FilesystemLoader, Environment };

/**
 * A test generation object. Normally, this is automatically called when running
 * the Gentry executable.
 */
abstract class Generator
{
    /** @var int */
    const AS_INSTANCE = 1;

    /** @var int */
    const AS_RETURNCHECK = 2;

    private stdClass $config;

    private ReflectionClass $objectUnderTest;

    private array $features = [];

    /**
     * Constructor. Pass the configuration object.
     *
     * @param stdClass $config
     * @return void
     */
    public function __construct(stdClass $config)
    {
        $this->config = $config;
        $loader = new FilesystemLoader($this->getTemplatePath());
        $this->twig = new Environment($loader, ['cache' => false]);
    }

    /**
     * Generates stub test methods for all found features.
     *
     * @param ReflectionClass $class The reflection of the object or trait under
     *  test.
     * @param array $methods Array of reflected methods to generate test
     *  skeletons for.
     * @param array $uncovered Array of uncovered method calls.
     * @return self
     */
    public function generate(ReflectionClass $class, array $methods, array $uncovered) : self
    {
        $this->objectUnderTest = $class;
        $this->features = [];
        foreach ($methods as $method) {
            if (isset($uncovered[$method->name]) && $uncovered[$method->name]) {
                $this->addFeature($class, $method, $uncovered[$method->name]);
            }
        }
        return clone $this;
    }

    /**
     * Renders the testing code according to the supplied template.
     *
     * @return string
     */
    public function render() : string
    {
        return $this->twig->render('template.html.twig', [
            'namespace' => $this->config->namespace ?? null,
            'objectUnderTest' => $this->objectUnderTest->name,
            'features' => $this->features,
        ]);
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
                $this->convertTestNameToFilename($this->objectUnderTest->getName()),
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
     * Internal method to add a testable feature.
     *
     * @param string The reflection of the object or trait to test a feature on.
     * @param ReflectionMethod $method Reflection of the feature to test.
     * @param array $calls Array of possible types of calls.
     * @return void
     */
    private function addFeature(ReflectionClass $class, ReflectionMethod $method, array $calls) : void
    {
        Formatter::out("<gray> Adding tests for feature <magenta>{$class->name}::{$method->name}\n");
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
                    $arglist[] = $this->getDefaultForType($p, self::AS_INSTANCE);
                }
            }
            $expectedResult = 'true';
            if ($method->hasReturnType()) {
                $type = $method->getReturnType()->getName();
                $expectedResult = $this->getDefaultForType($type, self::AS_RETURNCHECK);
            }
            $this->features[$method->name]->calls[] = (object)[
                'name' => $method->name,
                'parameters' => implode(', ', $arglist),
                'expectedResult' => $expectedResult,
                'isStatic' => $method->isStatic(),
            ];
        }
    }

    /**
     * Get a string representation of a default value for a given type.
     *
     * @param string $type
     * @param int $mode
     * @return string
     */
    private function getDefaultForType(string $type, int $mode = 0) : string
    {
        $isClass = false;
        switch ($type) {
            case 'string': $value = "'blarps'"; break;
            case 'int': $value = '1'; break;
            case 'float': $value = '1.1'; break;
            case 'mixed': $value = "'MIXED'"; break;
            case 'callable': $value = 'function () {}'; break;
            case 'bool': $value = 'true'; break;
            case 'array': $value = '[]'; break;
            case 'void': $value = 'null'; break;
            default:
                if (class_exists($type)) {
                    $isClass = true;
                }
                $value = $type;
        }
        switch ($mode) {
            case self::AS_INSTANCE:
                if ($isClass) {
                    return "new $value";
                } else {
                    return $value;
                }
            case self::AS_RETURNCHECK:
                if ($isClass) {
                    return "\$result instanceof $value";
                } elseif ($value === 'array') {
                    return "is_array(\$result)";
                } elseif ($value == 'callable') {
                    return "is_callable(\$result)";
                } else {
                    return "\$result === $value";
                }
            default: return $type;
        }
    }

    /**
     * Normalize the given string for use in the filesystem.
     *
     * @param string $name An object's name.
     * @return string
     */
    abstract protected function convertTestNameToFilename(string $name) : string;

    /**
     * Returns the path where the templates are stored.
     */
    abstract protected function getTemplatePath() : string;
}

