<?php

namespace Gentry\Cache;

use Psr\Cache\CacheItemInterface;

class Item implements CacheItemInterface
{
    /**
     * Constructor. Pass the desired key and value.
     *
     * @param string $key
     * @param mixed $value This _must_ be serializable.
     */
    public function __construct($key, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    /** 
     * @var string This item's unique key.
     */
    private $key;

    /**
     * @var mixed This item's value. Can be anything *as long as it satisties
     *  is_serializable*.
     */
    private $value;

    public function getKey()
    {
        return $this->key;
    }

    public function get()
    {
        return $this->value;
    }

    public function isHit()
    {
        return true; // for now
    }

    public function set($value)
    {
        $this->value = $value;
    }

    /**
     * Not implemented for this cache interface.
     */
    public function expiresAt($expiration)
    {
    }

    /**
     * Not implemented for this cache interface.
     */
    public function expiresAfter($time)
    {
    }
}

