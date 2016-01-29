<?php

namespace Gentry;

use ReflectionClass;
use ReflectionParameter;

class Argument
{
    private $reflection;
    private $fallback;

    public function __construct(ReflectionParameter $param, $fallback)
    {
        $this->reflection = $param;
        $this->fallback = $fallback;
    }

    public function __toString()
    {
        $out = $this->getType().' ';
        if ($this->isPassedByReference()) {
            $out .= '&';
        }
        $out .= '$'.$this->reflection->name;
        if ($default = $this->getDefault()) {
            $out .= " = $default";
        }
        return trim(preg_replace("@\s{2}@", ' ', $out));
    }

    /**
     * Guesstimate if the argument should be passed by reference. This is true
     * for objects with constructor parameters without defaults, as well as
     * other non-guessable defaults.
     */
    public function isPassedByReference()
    {
        if ($class = $this->reflection->getClass()) {
            $reflection = (new ReflectionClass($class))->getConstructor();
            foreach ($reflection->getParameters() as $param) {
                if (!$param->isDefaultValueAvailable()) {
                    return true;
                }
            }
            return false;
        }
        if ($this->reflection->isDefaultValueAvailable()) {
            return false;
        }
        // No default defined, but let's see if we can guess...
        if ($this->reflection->isArray()) {
            return false;
        }
        if (version_compare(phpversion(), '7.0', '>=')
            and $type = $this->reflection->getType()
        ) {
            return false;
        }
        if ($extracted = $this->extractParameterData()) {
            return false;
        }
        return true;
    }

    /**
     * Return the default value for the requested parameter.
     *
     * @return string A stringified version of the default value.
     */
    public function getDefault()
    {
        if ($this->reflection->isDefaultValueAvailable()
            and $default = $this->reflection->getDefaultValue()
        ) {
            if (is_scalar($default)) {
                return $default;
            } else {
                return $this->tostring($default);
            }
        }
        if ($this->reflection->isArray()) {
            return '[]';
        }
        if ($this->isPassedByReference()) {
            return 'null';
        } elseif ($this->reflection->getClass()) {
            // This gets injected as a new instance.
            return null;
        }
        $type = $this->getType();
        if (!$type) {
            $type = $this->extractParameterData();
        }
        // Guesstimated values:
        switch ($type) {
            case 'int': case 'integer': case 'float':
                return 0;
            case 'string':
                return "''";
            case 'array':
                return '[]';
        }
        return null;
    }

    private function getType()
    {
        if ($class = $this->reflection->getClass()) {
            return $class->name;
        }
        if ($this->reflection->isArray()) {
            return 'array';
        }
        if (version_compare(phpversion(), '7.0', '>=')
            and $type = $this->reflection->getType()
        ) {
            return $type;
        }
        return '';
    }

    private function extractParameterData()
    {
        $name = $this->reflection->name;
        if (!preg_match(
            "#@param\s+(.*?)\s+\\$$name(.*?)@#ms",
            "{$this->fallback}@",
            $matches
        )) {
            return null;
        }
        $types = explode('|', $matches[1]);
        return $types[0];
    }
    
    private function tostring($value)
    {
        if (!isset($value)) {
            return 'null';
        }
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }
        if (is_array($value)) {
            $out = '[';
            $i = 0;
            foreach ($value as $key => $entry) {
                if ($i) {
                    $out .= ', ';
                }
                $out .= $key.' => '.$this->tostring($entry);
                $i++;
            }
            $out .= ']';
            return $out;
        }
    }
}

