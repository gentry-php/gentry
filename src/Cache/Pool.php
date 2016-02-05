<?php

namespace Gentry\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;
use Dabble\Adapter\Sqlite;
use Dabble\Query\Exception;
use Dabble\Query\DeleteException;

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
     * @var Dabble\Adapter\Sqlite Static link to the SQLite adapter.
     */
    private static $db;

    public function __construct()
    {
        $this->client = getenv("GENTRY_CLIENT");
        if (!isset(self::$db)) {
            self::$db = new Sqlite(getenv("GENTRY_VENDOR").'/gentry.sq3');
            self::$db->exec("CREATE TABLE IF NOT EXISTS items (
                pool VARCHAR(6),
                keyname VARCHAR(32),
                value TEXT,
                PRIMARY KEY(pool, keyname)
            )");
        }
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
        try {
            return unserialize(self::$db->fetchColumn(
                'items',
                'value',
                [
                    'pool' => $this->client,
                    'keyname' => $key,
                ]
            ));
        } catch (Exception $e) {
            throw new InvalidArgumentException;
        }
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
        try {
            self::$db->delete(
                'items',
                ['pool' => $this->client]
            );
        } catch (DeleteException $e) {
            // Nothing in cache, that's fine.
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    public function deleteItem($key)
    {
        try {
            self::$db->delete(
                'items',
                ['pool' => $this->client, 'keyname' => $key]
            );
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function deleteItems(array $keys)
    {
        try {
            self::$db->delete(
                'items',
                [
                    'pool' => $this->client,
                    'keyname' => ['IN' => $keys],
                ]
            );
        } catch (DeleteException $e) {
            // Nothing in cache, that's fine.
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    public function save(CacheItemInterface $item)
    {
        $this->deleteItem($item->getKey());
        try {
            self::$db->insert(
                'items',
                [
                    'pool' => $this->client,
                    'keyname' => $item->getKey(),
                    'value' => serialize($item),
                ]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
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

