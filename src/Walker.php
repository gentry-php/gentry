<?php

namespace Gentry;

use ReflectionClass;

abstract class Walker
{
    public static function walk($dir, callable $callback)
    {
        $d = Dir($dir);
        $entries = [];
        while (false !== ($entry = $d->read())) {
            if ($entry{0} == '.') {
                continue;
            }
            if (is_dir("$dir/$entry")) {
                self::walk("$dir/$entry", $callback);
            } elseif (substr($entry, -4) == '.php') {
                $old = get_declared_classes();
                ob_start();
                @require_once "$dir/$entry";
                ob_end_clean();
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

