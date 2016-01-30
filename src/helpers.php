<?php

namespace Gentry;

use Ansi;
use Reflector;

/**
 * Output $text to the specified $out, with added ANSI colours and the lines
 * intelligently wrapped.
 */
function out($text, $out = STDOUT)
{
    $text = str_replace("\n", PHP_EOL, $text);
    $endsWithNewline = substr($text, -1) == PHP_EOL;
    $text = Ansi::tagsToColors(rtrim($text));
    preg_match("@^(\s*\**\s*)@", $text, $indent);
    $lines = [];
    $line =& $lines[];
    foreach (preg_split(
        "@\s+@m",
        preg_replace("@^(\s*\**\s*)@", '', $text)
    ) as $word) {
        if (strlen(cleanOutput("$line $word")) > 80) {
            $line =& $lines[];
        }
        if (!strlen($line)) {
            if ($indent) {
                if (count($lines) == 1) {
                    $line .= $indent[1];
                } else {
                    $line .= str_repeat(' ', strlen($indent[1]));
                }
            }
            $line .= $word;
            continue;
        } else {
            $line .= " $word";
        }
    }
    // PHP's output buffering doesn't catch fwrite(STDOUT, ...), so we wrap it.
    $output = function ($text) use ($out) {
        if ($out == STDOUT) {
            echo $text;
        } else {
            fwrite($out, $text);
        }
    };
    $output(implode(PHP_EOL, $lines).($endsWithNewline ? PHP_EOL : ''));
    $output(Ansi::tagsToColors('<reset>'));
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

