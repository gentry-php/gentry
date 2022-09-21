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

    public function __invoke()
    {
        if (!ini_get('date.timezone')) {
            ini_set('date.timezone', 'UTC');
        }
        $start = microtime(true);

        Formatter::out("\n<magenta>Gentry ".self::VERSION." by Marijn Ophorst\n\n");

        $config = $this->checkConfigFile();

        Formatter::out("<green>Running unit tests from <darkGray>{$config->test}<green>...\n");
        $shm_key = ftok(__FILE__, 't');
        $shm = shmop_open($shm_key, 'c', 0644, 1024 * 1024);
        exec($config->test);
        try {
            $coveredFeatures = unserialize(shmop_read($shm, 0, 1024 * 1024));
        } catch (ErrorException $e) {
            $coveredFeatures = [];
        }
        shmop_delete($shm);

        $sourcecode = new Sourcecode($config);
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
        if (VERBOSE) {
            Formatter::out("\n");
            foreach ($uncovered as $class => $methods) {
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
        $total = 0;
        array_walk($coveredFeatures, function ($feature, $name1) use (&$total) {
            array_walk($feature, function ($method, $name2) use (&$total, $name1) {
                $total += count($method);
            });
        });
        $totalU = 0;
        array_walk($uncovered, function ($feature, $name1) use (&$totalU) {
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
            microtime(true) - $start,
            memory_get_peak_usage(true) / 1048576
        ));

        if ($totalU) {
            Formatter::out("\n");
            $yesno = Formatter::ask("Attempt to generate missing tests? [Y/n] ", [true => 'Y', 'n']);
            if ($yesno == 'n') {
                Formatter::out("\n<darkGray>Ok, suit yourself!\n\n");
            } else {
                if (!$config->templates) {
                    Formatter::out("\n<red>Error: no templates defined.\n\n");
                } else {
                    $start = microtime(true);
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
    }

    private function checkConfigFile() : stdClass
    {
        $config = 'Gentry.json';
        define('Gentry\Gentry\VERBOSE', $this->verbose);
        try {
            $config = (object)(array)(new Kingconf\Config($config));
        } catch (Kingconf\Exception $e) {
            Formatter::out("<red>Error: <reset> Config file $config not found or invalid.\n", STDERR);
            die(1);
        }
        $config->src = is_array($config->src) ? $config->src : [$config->src];
        $config->templates = $config->templates ?? [];
        if (isset($config->bootstrap)) {
            $bootstrap = is_array($config->bootstrap) ? $config->bootstrap : [$config->bootstrap];
            foreach ($bootstrap as $file) {
                require_once $file;
            }
        }
        return $config;
    }
}

