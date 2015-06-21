<?php

namespace Gentry;

use ReflectionClass;
use Exception;

abstract class Walker
{
    public static function walk($dir, callable $callback, $strip = null, $verbose = null)
    {
        $d = Dir($dir);
        $entries = [];
        while (false !== ($entry = $d->read())) {
            if ($entry{0} == '.') {
                continue;
            }
            if (is_dir("$dir/$entry")) {
                self::walk("$dir/$entry", $callback, $strip, $verbose);
            } elseif (substr($entry, -4) == '.php') {
                $old = get_declared_classes();
                $path = "$dir/$entry";
                if (isset($strip)) {
                    $path = preg_replace("@^$strip/*@", '', $path);
                }
                if ($verbose) {
                    echo "$path... ";
                    require_once $path;
                    echo "ok\n";
                } else {
                    ob_start();
                    try {
                        @require_once $path;
                    } catch (Exception $e) {
                    }
                    ob_end_clean();
                }
                $new = get_declared_classes();
                foreach (array_diff($new, $old) as $added) {
                    $reflected = new ReflectionClass($added);
                    if ($reflected->isInternal()
                        || $reflected->isTrait()
                        || $reflected->isInterface()
                        || $reflected->isAbstract()
                    ) {
                        continue;
                    }
                    $callback($reflected);
                }
            }
        }
    }
}

