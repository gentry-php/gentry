<?php

namespace Gentry\Gentry;

use Gentry\Cache;
use Ansi;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ReflectionClass;
use ReflectionParameter;
use ReflectionException;
use Closure;
use zpt\anno\Annotations;
use Monomelodies\Kingconf;
use Exception;
use ErrorException;
use Monolyth\Cliff;
use stdClass;
use Generator;

/**
 * Tool to analyze your PHP source code and optionally setup unit tests.
 * Usage: $ vendor/bin/gentry COMMAND
 * "COMMAND" can be one of the following:
 * - analyze : list the classes/methods no unit tests have been found for yet.
 * - show : show which tests would be generated.
 * - generate : actually generate unit tests according to Gentry.json settings.
 * For more detailed information, see README.md.
 */
class Command extends Cliff\Command
{
    private const VERSION = "0.16.0";

    private stdClass $config;

    private array $coveredFeatures;

    private array $uncoveredFeatures;

    private float $start;

    private Sourcecode $sourcecode;

    public function __invoke(?string $command = null)
    {
        Formatter::out("\n<magenta>Gentry ".self::VERSION." by Marijn Ophorst\n\n");

        if ($command === null) {
            echo <<<EOT
Tool to analyze your PHP source code and optionally setup unit tests.
Usage: $ vendor/bin/gentry COMMAND
"COMMAND" can be one of the following:
- analyze : list the classes/methods no unit tests have been found for yet.
- show : show which tests would be generated.
- generate : actually generate unit tests according to Gentry.json settings.
For more detailed information, see README.md.

EOT;
            die();
        }
        if (!ini_get('date.timezone')) {
            ini_set('date.timezone', 'UTC');
        }
        $this->start = microtime(true);

        $this
            ->checkConfigFile()
            ->runUnitTests()
            ->analyzeSourcecode()
            ->showSummary()
            ;
        switch ($command) {
            case 'analyze':
                $this->showMissingTests();
                break;
            case 'show':
                $this->showGeneratedTests();
                break;
            case 'generate':
                $this->writeGeneratedTests();
                break;
        }
    }

    private function checkConfigFile() : self
    {
        $config = 'Gentry.json';
        try {
            $this->config = (object)(array)(new Kingconf\Config($config));
        } catch (Kingconf\Exception $e) {
            Formatter::out("<red>Error: <reset> Config file $config not found or invalid.\n", STDERR);
            die(1);
        }
        $this->config->src = is_array($this->config->src) ? $this->config->src : [$this->config->src];
        $this->config->templates = $this->config->templates ?? [];
        if (isset($this->config->bootstrap)) {
            $bootstrap = is_array($this->config->bootstrap) ? $this->config->bootstrap : [$this->config->bootstrap];
            foreach ($bootstrap as $file) {
                require_once $file;
            }
        }
        return $this;
    }

    private function runUnitTests() : self
    {
        Formatter::out("<green>Running unit tests from <darkGray>{$this->config->test}<green>...\n");
        exec($this->config->test);
        $this->coveredFeatures = unserialize(Logger::read());
        return $this;
    }

    private function analyzeSourcecode() : self
    {
        $this->sourcecode = new Sourcecode($this->config);
        Formatter::out(sprintf(
            "<gray>Found %d file%s with testable source code.\n",
            count($this->sourcecode->sources),
            count($this->sourcecode->sources) == 1 ? '' : 's'
        ));

        $uncovered = [];
        foreach ($this->sourcecode->sources as $file => $code) {
            if (!isset($this->coveredFeatures[$code[0]->name])) {
                $uncovered[$code[0]->name] = [];
            }
            foreach ($code[1] as $method) {
                if ($code[0]->name != $method->getDeclaringClass()->name) {
                    continue;
                }
                $calls = $method->getPossibleCalls(...$method->getParameters());
                if (!isset($this->coveredFeatures[$code[0]->name][$method->name])) {
                    $uncovered[$code[0]->name][$method->name] = $calls;
                    continue;
                }
                $uncovered[$code[0]->name][$method->name] = [];
                foreach ($calls as $call) {
                    if (!in_array($call, $this->coveredFeatures[$code[0]->name][$method->name])) {
                        $uncovered[$code[0]->name][$method->name][] = $call;
                    }
                }
            }
        }
        array_walk($uncovered, fn (&$calls) => $calls = array_filter($calls, fn ($untested) => count($untested) > 0));
        $this->uncoveredFeatures = array_filter($uncovered, fn ($calls) => count($calls) > 0);
        return $this;
    }

    private function showMissingTests() : self
    {
        Formatter::out("\n");
        foreach ($this->uncoveredFeatures as $class => $methods) {
            foreach ($methods as $name => $calls) {
                foreach ($calls as $call) {
                    Formatter::out(sprintf(
                        "<cyan>Missing test for %s::%s (%s).\n",
                        $class,
                        $name,
                        $call ? implode(', ', $call) : 'void'
                    ));
                }
            }
        }
        Formatter::out("\n");
        return $this;
    }
    
    private function showSummary() : void
    {
        $total = 0;
        array_walk($this->coveredFeatures, function ($feature) use (&$total) {
            array_walk($feature, function ($method) use (&$total) {
                $total += count($method);
            });
        });
        $totalU = 0;
        array_walk($this->uncoveredFeatures, function ($feature) use (&$totalU) {
            array_walk($feature, function ($method) use (&$totalU) {
                $totalU += count($method);
            });
        });
        if ($totalU == 0) {
            $color = 'green';
        } elseif ($totalU > $total) {
            $color = 'darkRed';
        } elseif ($total > $totalU / 2) {
            $color = 'darkGreen';
        } else {
            $color = 'red';
        }
        Formatter::out(sprintf(
            "<blue>Code coverage: ~<%s>%d <blue>of <cyan>%s <%s>(%0.2f%%)\n",
            $color,
            $total,
            $total + $totalU,
            $color,
            $total ? $total / ($total + $totalU) * 100 : 0
        ));
        Formatter::out("\n");
        Formatter::out(sprintf(
            "\n<magenta>Took %0.2f seconds, memory usage %4.2fMb.\n\n",
            microtime(true) - $this->start,
            memory_get_peak_usage(true) / 1048576
        ));
    }

    private function showGeneratedTests() : void
    {
        foreach ($this->generateTests() as $test) {
            $test = $test->render();
            fwrite(STDOUT, <<<EOT
--------
$test
--------

EOT
            );
        }
    }

    private function writeGeneratedTests() : void
    {
        foreach ($this->generateTests() as $test) {
            $test->write();
        }
    }

    private function generateTests() : Generator
    {
        if (!$this->config->generator) {
            Formatter::out("\n<red>Error: no generator defined.\n\n");
        } else {
            $class = $this->config->generator.'\Generator';
            $generator = new $class($this->config);
            foreach ($this->sourcecode->sources as $filename => $data) {
                if (isset($this->uncoveredFeatures[$data[0]->name]) && $this->uncoveredFeatures[$data[0]->name]) {
                    yield $generator->generate($data[0], $data[1], $this->uncoveredFeatures[$data[0]->name]);
                }
            }
        }
    }
}

