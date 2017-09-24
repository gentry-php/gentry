<?php

namespace Gentry\Gentry;

use Ansi;
use Reflector;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use zpt\anno\Annotations;

/**
 * Output $text to the specified $out, with added ANSI colours.
 *
 * @param string $text
 * @param resource $out
 */
function out(string $text, $out = STDOUT)
{
    if (!isset($output)) {
        $output = function ($text) use ($out) {
            fwrite($out, $text);
        };
    }

    $text = str_replace("\n", PHP_EOL, $text);
    $text = Ansi::tagsToColors($text);
    fwrite($out, $text);
}

function cleanOutput(string $string) : string
{
    return preg_replace('@\\033\[[\d;]*m@m', '', rtrim($string));
}

function cleanDocComment(Reflector $reflection, bool $strip_annotations = true) : string
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

function ask(string $question, array $options) : string
{
    out($question);
    fscanf(STDIN, '%s\n', $answer);
    if ($answer === null) {
        $answer = $options[true];
    }
    if (!in_array($answer, $options)) {
        return ask($question, $options);
    }
    return $answer;
}

