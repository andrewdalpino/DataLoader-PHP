<?php

namespace AndrewDalpino\DataLoader;

use InvalidArgumentException;
use IteratorAggregate;
use ArrayIterator;
use Countable;

class Buffer implements IteratorAggregate, Countable
{
    /**
     * The keys that are in the buffer.
     *
     * @var array
     */
    protected $keys = [
        //
    ];

    /**
     * Initialize the buffer.
     *
     * @return self
     */
    public static function init() : Buffer
    {
        return new self();
    }

    /**
     * @param  iterable  $keys
     * @return void
     */
    public function __construct(iterable $keys = [])
    {
        foreach ($keys as $key) {
            $this->enqueue($key);
        }
    }

    /**
     * Add a unique key to the end of the buffer.
     *
     * @param  mixed  $key
     * @return self
     */
    public function enqueue($key) : Buffer
    {
        if (! is_int($key) && ! is_string($key)) {
            throw new InvalidArgumentException('Key must be an integer or string type, ' . gettype($key) . ' found.');
        }

        array_push($this->keys, $key);

        return $this;
    }

    /**
     * Dequeue a number of keys from the front of the buffer.
     *
     * @param  int  $limit
     * @return array
     */
    public function dequeue(int $limit = 1) : array
    {
        return array_splice($this->keys, 0, $limit);
    }

    /**
     * Remove duplicate keys from the buffer.
     *
     * @return self
     */
    public function deduplicate() : Buffer
    {
        $this->keys = array_values(array_unique($this->keys));

        return $this;
    }

    /**
     * Return the buffer with keys not in the given array.
     *
     * @param  array  $keys
     * @return self
     */
    public function diff(array $keys) : Buffer
    {
        return new self(array_diff($this->keys, $keys));
    }

    /**
     * The last key to be added to the buffer.
     *
     * @return mixed
     */
    public function last()
    {
        return end($this->keys);
    }

    /**
     * Dump the contents of the buffer.
     *
     * @return array
     */
    public function dump() : array
    {
        return $this->keys;
    }

    /**
     * Remove all the keys in the buffer.
     *
     * @return self
     */
    public function flush() : Buffer
    {
        $this->keys = [];

        return $this;
    }

    /**
     * The number of keys in the buffer.
     *
     * @return int
     */
    public function count() : int
    {
        return count($this->keys);
    }

    /**
     * Get an iterator for the keys in the buffer.
     *
     * @return \ArrayIterator
     */
    public function getIterator() : ArrayIterator
    {
        return new ArrayIterator($this->keys);
    }
}
