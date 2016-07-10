<?php

namespace Gentry\Gentry;

use ReflectionClass;
use ReflectionParameter;

/**
 * Internal helper class representing an argument to a generated test stub.
 * Normally called automatically when running in generate mode.
 */
class Argument
{
    private $reflection;
    private $fallback;

    /**
     * Constructor.
     *
     * @param ReflectionParameter $param Reflection of the parameter this
     *  object will represent.
     * @param string $fallback The doccomment (if any) specified for the method
     *  the parameter belongs to, for fallback guesstimation.
     */
    public function __construct(ReflectionParameter $param, $fallback)
    {
        $this->reflection = $param;
        $this->fallback = $fallback;
    }

    /**
     * Return the entire argument in a format that can be injected into the
     * method declaration.
     *
     * @return string
     */
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
     *
     * @return bool
     */
    public function isPassedByReference()
    {
        if ($class = $this->reflection->getClass()
            and $class->isTrait()
        ) {
            return true;
        }
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

    /**
     * Internal helper method to guesstimate the argument's type hint.
     *
     * @return string
     */
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

    /**
     * Internal helper function to guesstimate the argument's type from the
     * doccomment `$fallback`, as annotated by `@param [type]`.
     *
     * @return string|null The annotated type if found, else null.
     */
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
 
    /**
     * Internal helper method to render a PHP variable as a string.
     *
     * @param mixed $value The value to render.
     * @return string An echo'able representation.
     */
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

