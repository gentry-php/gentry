<?php

namespace Gentry\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;
use ErrorException;

/**
 * A simple cache pool for Gentry.
 *
 * Unlike a "serious" cache implementation, this simply stores cached values in
 * an SQLite database `gentry.sq3` in your `vendor` dir.
 */
class Pool implements CacheItemPoolInterface
{
    /**
     * @var string The client ID for this set of test runs.
     */
    private $client;

    /**
     * @var array Psr\Cache\CacheItemInterface Array of deferred cache items.
     */
    private $deferred = [];

    /**
     * @var string Full pathname of the temporary storage file.
     */
    private static $path;

    /**
     * @var array Key/value hash of the current cache contents.
     */
    private static $cache;

    public function __construct()
    {
        $this->client = getenv("GENTRY_CLIENT");
        self::$path = sys_get_temp_dir()."/{$this->client}.cache";
        self::$cache = [];
        if (file_exists(self::$path)) {
            self::$cache = unserialize(file_get_contents($path));
        } else {
            file_put_contents(self::$path, serialize(self::$cache));
            chmod(self::$path, 0666);
        }            
    }

    public function __destruct()
    {
        file_put_contents(self::$path, serialize(self::$cache));
    }

    public static function getInstance()
    {
        static $pool;
        if (!isset($pool)) {
            $pool = new static;
        }
        return $pool;
    }

    public function getItem($key)
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        throw new InvalidArgumentException($key);
    }

    public function getItems(array $keys = [])
    {
        if (!$keys) {
            return [];
        }
        $return = [];
        foreach ($keys as $key) {
            try {
                $return[] = $this->getItem($key);
            } catch (InvalidArgumentException $e) {
                $return[] = new Item($key, null);
            }
        }
        return $return;
    }

    public function hasItem($key)
    {
        return isset(self::$cache[$key]);
    }

    public function clear()
    {
        self::$cache = [];
        return true;
    }

    public function deleteItem($key)
    {
        unset(self::$cache[$key]);
        return true;
    }

    public function deleteItems(array $keys)
    {
        array_walk($keys, function ($key) {
            unset(self::$cache[$key]);
        });
        return true;
    }

    public function save(CacheItemInterface $item)
    {
        self::$cache[$item->getKey()] = $item;
        file_put_contents(self::$path, serialize(self::$cache));
        return true;
    }

    public function saveDeferred(CacheItemInterface $item)
    {
        $this->deferred[] = $item;
    }

    public function commit()
    {
        while ($item = array_shift($this->deferred)) {
            $this->save($item);
        }
        return true;
    }
}

