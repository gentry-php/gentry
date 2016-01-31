<?php

namespace Gentry\Test;

class Executable extends Feature
{
    private $command;

    public function __construct($description, $target, &$command)
    {
        parent::__construct($description, $target, '', '');
        $this->command = $command;
    }

    /**
     * Get a hash of actual script execution results.
     *
     * @param array $args Arguments to test with.
     * @return array A hash with value and output results. Obviously scripts
     *  can't throw exceptions, so it's always `null`.
     */
    public function actual(array $args)
    {
        $actual = ['thrown' => null, 'out' => '', 'result' => false];
        if (!is_string($args[$this->target])) {
            return $actual;
        }
        ob_start();
        passthru(getcwd()."/{$args[$this->target]}", $return);
        $actual['result'] = $return;
        $actual['out'] = \Gentry\cleanOutput(ob_get_clean());
        return $actual;
    }

    public function testedFeature()
    {
        return $this->command;
    }
}

