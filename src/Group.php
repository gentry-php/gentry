<?php

namespace Gentry;

use ReflectionFunction;
use zpt\anno\Annotations;

class Group
{
    private $tests = [];

    public function __construct($target, $inject, array $tests)
    {
        foreach ($tests as $test) {
            $reflection = new ReflectionFunction($test);
            $this->tests[] = new Test($target, $reflection, $inject);
        }
    }

    public function run(&$passed, &$failed)
    {
        foreach ($this->tests as $test) {
            $test->run($passed, $failed);
        }
    }
}

