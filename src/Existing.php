<?php

namespace Gentry;

use stdClass;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ReflectionClass;
use ReflectionException;
use zpt\anno\Annotations;

/**
 * Repository gathering all existing tests in specified directory.
 */
class Existing
{
    public function __construct(stdClass $config)
    {
        $tests = [];
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($config->tests),
            RecursiveIteratorIterator::LEAVES_ONLY
        ) as $file) {
            if ($file->isFile() && substr($file->getFilename(), -4) == '.php') {
                $filename = realpath($file);
                $code = file_get_contents($filename);
                $code = preg_replace("@/\*\*.*?\*/@ms", '', $code);
                $ns = '';
                if (preg_match("@namespace ((\w|\\\\)+)@", $code, $match)) {
                    $ns = $match[1].'\\';
                }
                if (preg_match("@class\s+(\w+)\s+@msi", $code, $match)) {
                    $class = "$ns{$match[1]}";
                    try {
                        $reflection = new ReflectionClass($class);
                        $annotations = new Annotations($class);
                        if (!$reflection->isAbstract()) {
                            $tests[$class] = $filename;
                        }
                    } catch (ReflectionException $e) {
                        out("<magenta>Warning: <gray>".$e->getMessage()."\n");
                    }
                }
            }
        }
        $this->tests = $tests;
    }
}

