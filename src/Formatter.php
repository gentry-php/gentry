<?php

namespace Gentry\Gentry;

use Ansi;
use Reflector;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use zpt\anno\Annotations;

abstract class Formatter
{
    /**
     * Output $text to the specified $out, with added ANSI colours.
     *
     * @param string $text
     * @param resource $out
     */
    public static function out(string $text, $out = STDOUT)
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

    /**
     * Output `$question` to STDOUT and wait for an answer.
     *
     * @param string $question
     * @param array $options Array of valid options, e.g. `['Y', 'n']`
     * @return string The selected answer
     */
    public static function ask(string $question, array $options) : string
    {
        self::out($question);
        fscanf(STDIN, '%s\n', $answer);
        if ($answer === null) {
            $answer = $options[true];
        }
        if (!in_array($answer, $options)) {
            return self::ask($question, $options);
        }
        return $answer;
    }
}

