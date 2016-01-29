<?php

namespace Gentry;

use stdClass;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ReflectionClass;
use ReflectionMethod;
use zpt\anno\Annotations;
use SplFileInfo;

/**
 * Repository gathering all source files in specified directory.
 */
class Sourcecode
{
    public function __construct(stdClass $config)
    {
        $sources = [];
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($config->src),
            RecursiveIteratorIterator::LEAVES_ONLY
        ) as $file) {
            if (!$this->isPhp($file)) {
                continue;
            }
            if (!($reflection = $this->extractTestableClass($file))) {
                continue;
            }
            $annotations = new Annotations($reflection);
            if (isset($annotations['Untestable'])) {
                continue;
            }
            if ($methods = $this->getTestableMethods($reflection)) {
                $sources["$file"] = [$reflection->name, $methods];
            }
        }
        $this->sources = $sources;
    }

    protected function isPhp(SplFileInfo $file)
    {
        return $file->isFile() && substr($file->getFilename(), -4) == '.php';
    }

    protected function extractTestableClass(SplFileInfo $file)
    {
        $filename = realpath($file);
        $code = file_get_contents($filename);
        $code = preg_replace("@/\*\*.*?\*/@ms", '', $code);
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

    protected function getTestableMethods(ReflectionClass $reflection)
    {
        $methods = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $annotations = new Annotations($method);
            if (isset($annotations['Untestable'])) {
                continue;
            }
            if ($method->isInternal()) {
                continue;
            }
            if ($method->getDeclaringClass()->name != $reflection->name) {
                continue;
            }
            if ($method->name{0} == '_') {
                // Assume we never want to test these "private" or magic
                // methods.
                continue;
            }
            $doccomments = $method->getDocComment();
//            if ($doccomments
//                && preg_match('/@return ([\w|]*?)\s/ms', $doccomments, $returns)
//            ) {
            $methods[] = $method;
//            } elseif ($verbose) {
//                out("<gray>$ns$class::{$method->name} should annotate its @return value.\n");
//            }
        }
        return $methods ?: null;
    }
}

