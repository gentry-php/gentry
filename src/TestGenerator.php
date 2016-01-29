<?php

namespace Gentry;

use ReflectionMethod;
use ReflectionParameter;

class TestGenerator
{
    private $path;
    private $name;
    private $features = [];

    public function __construct($path)
    {
        $this->path = $path;
        $this->name = "Gentry_".md5(time());
    }

    /**
     * @param string $class
     */
    public function generate($class, array $methods)
    {
        foreach ($methods as $method) {
            $this->addFeature($class, $method);
        }
    }

    private function addFeature($class, ReflectionMethod $method)
    {
        if (VERBOSE) {
            out("<gray> Adding feature <magenta>$class::{$method->name}\n");
        }
        $md5 = md5(microtime());
        $tested = $method->name;
        $arguments[] = "$class \$test";
        $fallback = cleanDocComment($method, false);
        foreach ($method->getParameters() as $param) {
            $arguments[] = sprintf(
                '%s$%s%s',
                $this->getArgumentType($param, $fallback),
                $param->name,
                $this->getArgumentDefault($param, $fallback)
            );
        }
        $arguments = implode(', ', $arguments);
        $return = "throw new \Exception(\"Incomplete test!\");";
        $this->features[] = <<<EOT
    /**
     * [GENERATED] {0}::$tested
     *
     * @Incomplete
     */
    public function gentry_$md5($arguments)
    {
        $return
    }
EOT;
    }

    private function getReturnValues(ReflectionMethod $method)
    {
    }

    private function getArgumentType(ReflectionParameter $param, $fallback)
    {
        if ($class = $param->getClass()) {
            return "{$class->name} ";
        }
        if ($param->isArray()) {
            return 'array ';
        }
        return '';
    }

    private function getArgumentDefault(ReflectionParameter $param, $fallback)
    {
        if ($param->isArray()) {
            return ' = []';
        }
        if ($param->getClass()) {
            return '';
        }
        if ($param->isDefaultValueAvailable()) {
            $default = $param->getDefaultValue();
            if (is_null($default)) {
                return ' = null';
            }
            if (is_numeric($default)) {
                return ' = '.$default;
            }
            if (is_string($default)) {
                return ' = "'.addslashes($default).'"';
            }
            return " = $default";
        }
        if (version_compare(phpversion(), '7.0', '>=')) {
            if ($type = $param->getType()) {
                switch ($type->__toString()) {
                    case 'int': return ' = 0';
                    case 'string': return ' = ""';
                }
            }
        }
        if ($type = $this->extractParameterData($param->name, $fallback)) {
            switch ($type) {
                case 'integer': return ' = 0';
                case 'string': return ' = ""';
                case 'boolean': case 'bool': return ' = true';
                case 'array': return ' = []';
            }
            if (class_exists($type)) {
                return '';
            }
            return '';
        }
    }

    private function extractParameterData($name, $fallback)
    {
        if (!preg_match(
            "#@param\s+(.*?)\s+\\$$name(.*?)@#ms",
            "$fallback@",
            $matches
        )) {
            return null;
        }
        $types = explode('|', $matches[1]);
        return $types[0];
    }

    public function write()
    {
        $file = "{$this->path}/{$this->name}.php";
        $code = <<<EOT
<?php

class %s
{
%s
}


EOT;
        $code = sprintf($code, $this->name, implode("\n\n", $this->features));
        file_put_contents($file, $code);
    }
}

