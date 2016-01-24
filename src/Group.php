<?php

namespace Gentry;

use ReflectionFunction;
use zpt\anno\Annotations;

class Group
{
    private $tests = [];
    private $testedFeatures = [];

    public function __construct($target, $inject, array $tests)
    {
        foreach ($tests as $test) {
            $reflection = new ReflectionFunction($test);
            $this->tests[] = new Test($target, $reflection, $inject);
        }
    }

    public function run(&$passed, array &$failed)
    {
        foreach ($this->tests as $test) {
            $test->run($passed, $failed);
            $this->testedFeatures = array_merge_recursive(
                $this->testedFeatures,
                $test->getTestedFeatures()
            );
        }
    }

    public function getTestedFeatures()
    {
        foreach ($this->testedFeatures as &$features) {
            $features = array_unique($features);
        }
        return $this->testedFeatures;
    }
}

