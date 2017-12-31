<?php

namespace AndrewDalpino\DataLoader;

use IteratorAggregate;
use ArrayIterator;
use ArrayAccess;
use Countable;

class Cache implements ArrayAccess, IteratorAggregate, Countable
{
    /**
     * The items contained in the collection.
     *
     * @var array
     */
    protected $items = [
        //
    ];

    /**
     * Initialize the cache.
     *
     * @return self
     */
    public static function init()
    {
        return new self();
    }

    /**
     * @param  array  $items
     * @return void
     */
    public function __construct(array $items = [])
    {
        $this->merge($items);
    }

    /**
     * Determine if an item exists in the collection by key.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function has($key) : bool
    {
        if ($this->offsetExists($key)) {
            return true;
        }

        return false;
    }

    /**
     * Put an item into the cache.
     *
     * @param  mixed  $key
     * @param  mixed|null  $value
     * @return self
     */
    public function put($key, $value = null) : Cache
    {
        $this->offsetSet($key, $value);

        return $this;
    }

    /**
     * Merge multiple items into the cache.
     *
     * @param  array  $keys
     * @return array
     */
    public function merge(array $items) : Cache
    {
        $this->items = array_replace($this->items, $items);

        return $this;
    }

    /**
     * Get an item from the cache by key.
     *
     * @param  mixed  $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->offsetGet($key) ?? null;
    }

    /**
     * Get multiple items from the cache by key.
     *
     * @param  array  $keys
     * @return array
     */
    public function mget(array $keys) : array
    {
        return array_intersect_key($this->items, array_flip($keys));
    }

    /**
     * Return all items in the cache.
     *
     * @return array
     */
    public function all() : array
    {
        return $this->items;
    }

    /**
     * Get the keys of all the items in the cache.
     *
     * @return array
     */
    public function keys() : array
    {
        return array_keys($this->items);
    }

    /**
     * Remove an item from the cache by key.
     *
     * @param  mixed  $key
     * @return self
     */
    public function forget($key) : Cache
    {
        $this->offsetUnset($key);

        return $this;
    }

    /**
     * Flush all the items from the cache.
     *
     * @return self
     */
    public function flush() : Cache
    {
        $this->items = [];

        return $this;
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Get an item at a given offset.
     *
     * @param  mixed  $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->items[$key] ?? null;
    }

    /**
     * Set the item at a given offset.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /**
     * Unset the item at a given offset.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key)
    {
        unset($this->items[$key]);
    }

    /**
     * Count the number of items in the cache.
     *
     * @return int
     */
    public function count() : int
    {
        return count($this->items);
    }

    /**
     * Get an iterator for the items in the cache.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }
}
