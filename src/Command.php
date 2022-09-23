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

class Command extends Cliff\Command
{
    private const VERSION = "0.16.0";

    public bool $verbose = false;

    private stdClass $config;

    private array $coveredFeatures;

    private array $uncoveredFeatures;

    private float $start;

    public function __invoke()
    {
        Formatter::out("\n<magenta>Gentry ".self::VERSION." by Marijn Ophorst\n\n");
        if (!ini_get('date.timezone')) {
            ini_set('date.timezone', 'UTC');
        }
        $this->start = microtime(true);

        $this
            ->checkConfigFile()
            ->runUnitTests()
            ->analyzeSourcecode()
            ->showMissingTests()
            ->showSummary()
            ;
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
        $sourcecode = new Sourcecode($this->config);
        Formatter::out(sprintf(
            "<gray>Found %d file%s with testable source code.\n",
            count($sourcecode->sources),
            count($sourcecode->sources) == 1 ? '' : 's'
        ));

        $uncovered = [];
        foreach ($sourcecode->sources as $file => $code) {
            if (!isset($coveredFeatures[$code[0]->name])) {
                $uncovered[$code[0]->name] = [];
            }
            foreach ($code[1] as $method) {
                if ($code[0]->name != $method->getDeclaringClass()->name) {
                    continue;
                }
                $calls = $method->getPossibleCalls(...$method->getParameters());
                if (!isset($coveredFeatures[$code[0]->name][$method->name])) {
                    $uncovered[$code[0]->name][$method->name] = $calls;
                    continue;
                }
                $uncovered[$code[0]->name][$method->name] = [];
                foreach ($calls as $call) {
                    if (!in_array($call, $coveredFeatures[$code[0]->name][$method->name])) {
                        $uncovered[$code[0]->name][$method->name][] = $call;
                    }
                }
            }
        }
        $this->uncoveredFeatures = $uncovered;
        return $this;
    }

    private function showMissingTests() : self
    {
        if ($this->verbose) {
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
        }
        Formatter::out("\n");
        return $this;
    }
    
    private function showSummary() : void
    {
        $total = 0;
        array_walk($this->coveredFeatures, function ($feature, $name1) use (&$total) {
            array_walk($feature, function ($method, $name2) use (&$total, $name1) {
                $total += count($method);
            });
        });
        $totalU = 0;
        array_walk($this->uncoveredFeatures, function ($feature, $name1) use (&$totalU) {
            array_walk($feature, function ($method, $name2) use (&$totalU, $name1) {
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
        if ($totalU) {
            Formatter::out("\n");
            $yesno = Formatter::ask("Attempt to generate missing tests? [Y/n] ", [true => 'Y', 'n']);
            if ($yesno == 'n') {
                Formatter::out("\n<darkGray>Ok, suit yourself!\n\n");
            } else {
                $this->generateTests();
            }
        }
    }

    private function generateTests() : void
    {
        if (!$config->templates) {
            Formatter::out("\n<red>Error: no templates defined.\n\n");
        } else {
            foreach ($config->templates as $template) {
                $generator = new Generator((object)$template);
                foreach ($sourcecode->sources as $filename => $data) {
                    if (isset($uncovered[$data[0]->name]) && $uncovered[$data[0]->name]) {
                        $generator->generate($data[0], $data[1], $uncovered[$data[0]->name]);
                    }
                }
            }
        }
    }
}

