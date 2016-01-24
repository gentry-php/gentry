<?php

namespace Gentry;

use Ansi;

function out($text, $out = STDOUT)
{
    $text = str_replace("\n", PHP_EOL, $text);
    echo Ansi::tagsToColors($text);
    echo Ansi::tagsToColors('<reset>');
}

function isEqual($a, $b)
{
    if (is_numeric($a) && is_numeric($b)) {
        return 0 + $a == 0 + $b;
    }
    if (is_object($a) && is_object($b)) {
        return $a == $b;
    }
    if (is_array($a) && is_array($b)) {
        return tostring($a) === tostring($b);
    }
    return $a === $b;
}

/**
 * @see https://coderwall.com/p/3j2hxq/find-and-format-difference-between-two-strings-in-php
 */
function strdiff($old, $new)
{
    $old = str_replace("\033[", "<gray>\\033[<reset>\033[", $old);
    $new = str_replace("\033[", "<gray>\\033[<reset>\033[", $new);
    $from_start = strspn($old ^ $new, "\0");
    $from_end = strspn(strrev($old) ^ strrev($new), "\0");

    $old_end = strlen($old) - $from_end;
    $new_end = strlen($new) - $from_end;

    $start = substr($new, 0, $from_start);
    $end = substr($new, $new_end);
    $new_diff = substr($new, $from_start, $new_end - $from_start);
    $old_diff = substr($old, $from_start, $old_end - $from_start);

    $new = "$start<red>$new_diff<reset>$end";
    $old = "$start<green>$old_diff<reset>$end";
    return [
        'old' => preg_replace("@ @ms", "<reset><bgYellow>.<reset>", $old),
        'new' => preg_replace("@ @ms", "<reset><bgYellow>.<reset>", $new),
    ];
}

function tostring($value)
{
    if (!isset($value)) {
        return 'NULL';
    }
    if ($value === true) {
        return 'true';
    }
    if ($value === false) {
        return 'false';
    }
    if (is_scalar($value)) {
        return $value;
    }
    if (is_array($value)) {
        $out = 'array(';
        $i = 0;
        foreach ($value as $key => $entry) {
            if ($i) {
                $out .= ', ';
            }
            $out .= $key.' => '.tostring($entry);
            $i++;
        }
        $out .= ')';
        return $out;
    }
    if (is_object($value)) {
        return "$value";
    }
}

function throwCompare($expected, $actual)
{
    if (isset($expected)) {
        $expected = get_class($expected);
    }
    if (isset($actual)) {
        $actual = get_class($actual);
    }
    return $expected === $actual;
}

