<?php

namespace Gentry\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;
use ErrorException;
use PDO;

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
     * @var PDO Static link to a PDO adapter for storage.
     */
    private static $db;

    public function __construct(PDO $pdo)
    {
        $this->client = getenv("GENTRY_CLIENT");
        self::$db = $pdo;
        self::$db->exec("CREATE TABLE IF NOT EXISTS _gentry_cache (
            pool VARCHAR(6),
            keyname VARCHAR(32),
            value TEXT,
            PRIMARY KEY(pool, keyname)
        )");
    }

    public static function getInstance(PDO $pdo = null)
    {
        static $pool;
        if (!isset($pool)) {
            $pool = new static($pdo);
        }
        return $pool;
    }

    public function getItem($key)
    {
        static $stmt;
        if (!isset($stmt)) {
            $stmt = self::$db->prepare("SELECT value FROM _gentry_cache
                WHERE pool = ? AND keyname = ?");
        }
        $stmt->execute([$this->client, $key]);
        if ($value = $stmt->fetchColumn()) {
            return unserialize($value);
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
        try {
            $this->getItem($key);
            return true;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    public function clear()
    {
        static $stmt;
        if (!isset($stmt)) {
            $stmt = self::$db->prepare("DELETE FROM _gentry_cache
                WHERE pool = ?");
        }
        $stmt->execute([$this->client]);
        return true;
    }

    public function deleteItem($key)
    {
        static $stmt;
        if (!isset($stmt)) {
            $stmt = self::$db->prepare("DELETE FROM _gentry_cache
                WHERE pool = ? AND keyname = ?");
        }
        $stmt->execute([$this->client, $key]);
        return true;
    }

    public function deleteItems(array $keys)
    {
        array_walk($keys, function ($key) {
            $this->deleteItem($key);
        });
        return true;
    }

    public function save(CacheItemInterface $item)
    {
        static $stmt;
        if (!isset($stmt)) {
            $stmt = self::$db->prepare("INSERT INTO _gentry_cache
                VALUES (?, ?, ?)");
        }
        $this->deleteItem($item->getKey());
        $stmt->execute([$this->client, $item->getKey(), serialize($item)]);
        return true;
    }

    public function saveDeferred(CacheItemInterface $item)
    {
        $this->deferred[] = $item;
    }

    public function commit()
    {
        while ($item = array_shift($this->deferred)) {
            if (!($this->save($item))) {
                array_unshift($this->deferred, $item);
                return false;
            }
        }
        return true;
    }
}

