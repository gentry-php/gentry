<?php

namespace Gentry;

use Ansi;
use Reflector;

function out($text, $out = STDOUT)
{
    $text = str_replace("\n", PHP_EOL, $text);
    echo Ansi::tagsToColors($text);
    echo Ansi::tagsToColors('<reset>');
}

function cleanOutput($string)
{
    return preg_replace('@\\033\[[\d;]*m@m', '', trim($string));
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

