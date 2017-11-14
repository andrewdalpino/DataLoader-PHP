<?php

namespace AndrewDalPino\DataLoader;

use IteratorAggregate;
use ArrayIterator;
use ArrayAccess;
use Traversable;
use Countable;

abstract class InMemoryCollection implements IteratorAggregate, ArrayAccess, Countable
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
     * @param  mixed  $items
     * @return void
     */
    protected function __construct($items = [])
    {
        $this->items = $this->getArrayableItems($items);
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
     * Put an item in the collection by key.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return self
     */
    public function put($key, $value)
    {
        $this->offsetSet($key, $value);

        return $this;
    }

    /**
     * Get an item from the collection by key.
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key)
    {
        if ($this->has($key)) {
            return $this->items[$key];
        }

        return null;
    }

    /**
     * Run a filter over each of the items in the collection.
     *
     * @param  callable  $filter
     * @return static
     */
    public function filter(callable $filter)
    {
        return new static(array_filter($this->items, $filter, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Get all of the items in the collection.
     *
     * @return array
     */
    public function all() : array
    {
        return $this->items;
    }

    /**
     * Merge the given items into the collection.
     *
     * @param  $mixed  $items
     * @return self
     */
    public function merge($items)
    {
        $this->items = array_merge($this->items, $this->getArrayableItems($items));

        return $this;
    }

    /**
     * Return a cross section of the collection.
     *
     * @param  int  $offset
     * @param  int  $length
     * @return static
     */
    public function slice($offset, $length = null)
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    /**
     * Return a number of items from the beginning of the collection before burning them.
     *
     * @param  int  $limit
     * @return static
     */
    public function take(int $limit)
    {
        $taken = $this->slice(0, $limit);

        $this->forget($taken->keys());

        return $taken;
    }

    /**
     * Get the keys of all the items in the collection.
     *
     * @return array
     */
    public function keys() : array
    {
        return array_keys($this->items);
    }

    /**
     * Key the reults using the return value from a callback.
     *
     * @param  callable  $keyBy
     * @return self
     */
    public function keyBy(callable $keyBy)
    {
        $results = [];

        foreach ($this->items as $key => $item) {
            $resolvedKey = $keyBy($item, $key);

            $results[$resolvedKey] = $item;
        }

        return new static($results);
    }

    /**
     * Get the items in the collection whose keys are not present in the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function diffKeys($items)
    {
        return new static(array_diff_key($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Count the number of items in the collection.
     *
     * @return int
     */
    public function count() : int
    {
        return count($this->items);
    }

    /**
     * Remove an item from the collection by key.
     *
     * @param  mixed  $keys
     * @return self
     */
    public function forget($keys)
    {
        foreach ((array) $keys as $key) {
            $this->offsetUnset($key);
        }

        return $this;
    }

    /**
     * Remove all the items in the collection.
     *
     * @return self
     */
    public function flush()
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
        return $this->items[$key];
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
     * Get an iterator for the items.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Results array of items from Collection or Arrayable.
     *
     * @param  mixed  $items
     * @return array
     */
    protected function getArrayableItems($items)
    {
        if (is_array($items)) {
            return $items;
        } elseif ($items instanceof self) {
            return $items->all();
        } elseif ($items instanceof Traversable) {
            return iterator_to_array($items);
        }

        return (array) $items;
    }
}
