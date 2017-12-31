<?php

namespace AndrewDalpino\DataLoader;

use InvalidArgumentException;
use IteratorAggregate;
use ArrayIterator;
use Countable;

class Cache implements IteratorAggregate, Countable
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
    public static function init() : Cache
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
     * Determine if an item exists in the cache.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function has($key) : bool
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Put an item in the cache.
     *
     * @param  mixed  $key
     * @param  mixed|null  $value
     * @return self
     */
    public function put($key, $value = null) : Cache
    {
        if (! is_int($key) && ! is_string($key)) {
            throw new InvalidArgumentException('Key must be an integer or string type, ' . gettype($key) . ' found.');
        }

        $this->items[$key] = $value;

        return $this;
    }

    /**
     * Merge multiple items into the cache.
     *
     * @param  array  $items
     * @return self
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
        return $this->items[$key] ?? null;
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
     * Remove a number of items from the cache by key.
     *
     * @param  mixed  $keys
     * @return self
     */
    public function forget($keys) : Cache
    {
        foreach ((array) $keys as $key) {
            unset($this->items[$key]);
        }

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
