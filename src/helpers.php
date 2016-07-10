<?php

namespace Gentry\Gentry;

use Ansi;
use Reflector;

/**
 * Output $text to the specified $out, with added ANSI colours and the lines
 * intelligently wrapped.
 */
function out($text, $out = STDOUT)
{
    static $col = 0;
    static $indent = '';
    static $output;
    if (!isset($output)) {
        $output = function ($text) use ($out) {
            fwrite($out, $text);
        };
    }

    $text = str_replace("\n", PHP_EOL, $text);
    $endsWithNewline = substr($text, -1) == PHP_EOL;
    if (substr($text, 0, 4) == '  * ') {
        $indent = '  * ';
        $text = substr($text, 4);
    }
    $text = Ansi::tagsToColors(rtrim($text));
    $lines = [];
    $line =& $lines[];
    foreach (preg_split("@\s+@m", trim($text)) as $word) {
        $len = strlen(cleanOutput($word));
        if (!$len) {
            if (strlen($word)) {
                $output($word);
            }
            continue;
        }
        if ($len + strlen($indent) + $col > 80) {
            if (!$col) {
                // Extremely long word. Just let it be then and
                // move to the next line.
                $output("$indent$word\n", $out);
                continue;
            }
            $output(PHP_EOL, $out);
            $col = 0;
            $indent = str_repeat(' ', strlen($indent));
            $output($indent.$word, $out);
            $col += strlen($indent) + $len;
        } else {
            if (!$col) {
                $output($indent, $out);
                $col += strlen($indent);
                $indent = str_repeat(' ', strlen($indent));
            }
            $space = true;
            if ($col == strlen($indent)
                || preg_match('@^[:!,;\?\.]@', cleanOutput($word))
            ) {
                $space = false;
            }
            $output(($space ? ' ' : '').$word, $out);
            $col += $len + 1;
        }
    }
    if ($endsWithNewline) {
        $output(PHP_EOL, $out);
        $col = 0;
        $indent = '';
    }
}

function cleanOutput($string)
{
    return preg_replace('@\\033\[[\d;]*m@m', '', rtrim($string));
}

function cleanDocComment(Reflector $reflection, $strip_annotations = true)
{
    $doccomment = $reflection->getDocComment();
    $doccomment = preg_replace("@^/\*\*@", '', $doccomment);
    $doccomment = preg_replace("@\*/$@m", '', $doccomment);
    if ($strip_annotations) {
        $doccomment = preg_replace("/^\s*\*\s*@\w+.*?$/m", '', $doccomment);
    }
    $doccomment = preg_replace("@^\s*\*\s*@m", '', $doccomment);
    $doccomment = trim(preg_replace("@\s{2,}@", ' ', $doccomment));
    return $doccomment;
}

