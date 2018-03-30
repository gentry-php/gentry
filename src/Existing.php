<?php

namespace Gentry\Gentry;

use stdClass;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ReflectionClass;
use ReflectionException;
use zpt\anno\Annotations;

/**
 * Repository gathering all existing tests in specified directory. You can
 * access the public `tests` property after construction.
 */
class Existing
{
    /**
     * Constructor.
     *
     * @param stdClass $config Configuration as read from Gentry.json
     * @return void
     */
    public function __construct(stdClass $config)
    {
        $tests = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($config->tests), RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
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
                        Formatter::out("<magenta>Warning: <gray>".$e->getMessage()."\n");
                    }
                }
            }
        }
        $this->tests = $tests;
    }
}

