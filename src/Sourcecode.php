<?php

namespace Gentry\Gentry;

use stdClass;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ReflectionClass;
use ReflectionMethod;
use zpt\anno\Annotations;
use SplFileInfo;

/**
 * Repository gathering all source files in specified directory. After
 * construction you may access the public `sources` property.
 */
class Sourcecode
{
    private $namespaces = [];

    /**
     * Constructor.
     *
     * @param stdClass $config Configuration as read from Gentry.json.
     */
    public function __construct(stdClass $config)
    {
        $reflections = [];
        $sources = [];
        foreach ($config->src as $src) {
            foreach (new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($src),
                RecursiveIteratorIterator::LEAVES_ONLY
            ) as $file) {
                if (!$this->isPhp($file)) {
                    continue;
                }
                foreach ($config->ignore ?? [] as $ignore) {
                    if (preg_match("@$ignore@", $file)) {
                        continue 2;
                    }
                }
                if (!($reflection = $this->extractTestableClass($file))) {
                    continue;
                }
                $annotations = new Annotations($reflection);
                if (isset($annotations['Untestable'])) {
                    continue;
                }
                $reflections[] = $reflection;
                if ($reflection->inNamespace()) {
                    $namespace = explode('\\', $reflection->getNamespaceName());
                    while ($namespace) {
                        $this->namespaces[] = implode('\\', $namespace);
                        array_pop($namespace);
                    }
                }
            }
            $this->namespaces = array_unique($this->namespaces);
            foreach ($reflections as $reflection) {
                if ($methods = $this->getTestableMethods($reflection)) {
                    $sources[$reflection->getFileName()] = [$reflection, $methods];
                }
            }
        }
        $this->sources = $sources;
    }

    /**
     * Internal helper to check if the passed file looks like a PHP file.
     *
     * @param SplFileInfo $file The file to check.
     * @return bool
     */
    protected function isPhp(SplFileInfo $file)
    {
        return $file->isFile() && substr($file->getFilename(), -4) == '.php';
    }

    /**
     * Internal helper method to extract the name of the testable class in the
     * file, if any.
     *
     * @param SplFileInfo $file The file from which to extract.
     * @return ReflectionClass|null A reflection of the found class, or null on
     *  failure.
     */
    protected function extractTestableClass(SplFileInfo $file)
    {
        $filename = realpath($file);
        $code = file_get_contents($filename);
        $code = preg_replace("@/\*.*?\*/@ms", '', $code);
        $code = preg_replace("@//.*?$@ms", '', $code);
        $ns = '';
        if (preg_match("@namespace ((\w|\\\\)+)@", $code, $match)) {
            $ns = $match[1].'\\';
        }
        $class = '';
        if (preg_match("@(class|trait)\s+(\w+)\s@", $code, $match)) {
            $class = $match[2];
        } else {
            return null;
        }
        if (!(preg_match_all(
            "@(?<!private|protected)\s*function\s+(\w+)\s*\(@",
            $code,
            $matches,
            PREG_SET_ORDER
        ))) {
            return null;
        }
        if (isset($config->ignore)
            && preg_match("@{$config->ignore}@", "$ns$class")
        ) {
            return null;
        }
        $reflection = new ReflectionClass("$ns$class");
        if ($reflection->isAbstract()) {
            return null;
        }
        return $reflection;
    }

    /**
     * Internal helper method to get the testable methods from a class.
     *
     * A method is considered testable when:
     * - It is public;
     * - It's not annotated with @Untestable;
     * - It's not an internal PHP method;
     * - Its name doesn't start with `_`.
     *
     * For inherited methods, the method is considered testable addionally when:
     * - The parent class itself is abstract;
     * - The parent class is in a "known namespace".
     *
     * The first condition operates on the assumption that a non-abstract parent
     * class should test the feature.
     *
     * The last condition prevents potentially testable methods from external
     * sources (most likely the `vendor` directory) to be included (it should be
     * assumed they have their own set of tests).
     *
     * @param ReflectionClass $reflection Reflected class to check methods on.
     * @return array|null An array of testable ReflectionMethods, or null if
     *  nothing could be found.
     */
    protected function getTestableMethods(ReflectionClass $reflection)
    {
        $methods = [];
        $source = file_get_contents($reflection->getFileName());
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $annotations = new Annotations($method);
            if (isset($annotations['Untestable'])) {
                continue;
            }
            if ($method->isInternal()) {
                continue;
            }
            $declaring = $method->getDeclaringClass();
            if ($declaring->name != $reflection->name) {
                if (!$declaring->isAbstract()) {
                    continue;
                }
                if ($declaring->inNamespace()
                    && !in_array(
                        $declaring->getNamespaceName(),
                        $this->namespaces
                    )
                ) {
                    continue;
                }

                if (!in_array(
                    $method,
                    $this->getTestableMethods($declaring) ?: []
                )) {
                    continue;
                }
            } elseif (strpos($source, "function {$method->name}(") === false) {
                // Method comes from a trait; these we skip for now.
                continue;
            }
            if ($method->name{0} == '_') {
                // Assume we never want to test these "private" or magic
                // methods.
                continue;
            }
            $doccomments = $method->getDocComment();
            $methods[] = $method;
        }
        return $methods ?: null;
    }
}

