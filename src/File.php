<?php

namespace Gentry;

use SplFileInfo;

class File extends SplFileInfo
{
    private $returned;
    private $output;
    private $environment = [];

    public function __construct(SplFileInfo $file)
    {
        parent::__construct($file->__toString());
    }

    public function setIncludeResults($returned, $output, array $environment)
    {
        $this->returned = $returned;
        $this->output = $output;
        $this->environment = $environment;
    }

    public function getReturnedValue()
    {
        return $this->returned;
    }

    public function __get($varname)
    {
        if (isset($this->environment[$varname])) {
            return $this->environment[$varname];
        }
        return null;
    }

    public function __isset($varname)
    {
        return isset($this->environment[$varname]);
    }

    public function replay()
    {
        echo $this->output;
    }
}

