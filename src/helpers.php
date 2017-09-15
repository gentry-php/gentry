<?php

namespace Gentry\Gentry;

use Ansi;
use Reflector;
use ReflectionParameter;

/**
 * Output $text to the specified $out, with added ANSI colours.
 */
function out($text, $out = STDOUT)
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

function getNormalisedType($type)
{
    if (is_object($type)) {
        return get_class($type);
    }
    $type = gettype($type);
    switch ($type) {
        case 'integer': return 'int';
        case 'boolean': return 'bool';
        default: return $type;
    }
}

function ask($question, array $options)
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

/**
 * Return an array of all possible combinations of variable types.
 *
 * @param ReflectionParameter ...$params Zero or more reflection parameters.
 * @return array Array of array of possible combination of parameter types
 *  this method accepts.
 */
function getPossibleCalls(ReflectionParameter ...$params) : array
{
    if (!count($params)) {
        return [[]];
    }
    $options = [];
    $opts = [];
    foreach ($params as $param) {
        if (!$param->hasType()) {
            $opts[] = 'mixed';
        } else {
            $opts[] = $param->getType()->__toString();
        }
    }
    $options[] = $opts;
    $last = array_pop($params);
    if ($last->isOptional() && !$last->isVariadic()) {
        $options = array_merge($options, getPossibleCalls(...$params));
    }
    return $options;
}

