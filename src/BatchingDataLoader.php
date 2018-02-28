<?php

namespace AndrewDalpino\DataLoader;

use InvalidArgumentException;
use UnexpectedValueException;
use RuntimeException;

class BatchingDataLoader
{
    /**
     * A hash map containing buffered keys.
     *
     * @var array
     */
    protected $buffer = [
        //
    ];

    /**
     * The memoized results of the batch queries.
     *
     * @var array
     */
    protected $loaded = [
        //
    ];

    /**
     * The closure used to batch load entities from a source.
     *
     * @var callable
     */
    protected $batchFunction;

    /**
     * The clorsure that returns the cache key of a loaded entity.
     *
     * @var callable
     */
    protected $cacheKeyFunction;

    /**
     * Options that adjust the runtime behavior of the loader.
     *
     * @var array
     */
    protected $options = [
        'batch_size' => 1000,
    ];

    /**
     * Instantiate a new dataloader with provided batch and cache key callbacks.
     *
     * @param  callable  $batchFunction
     * @param  callable|null  $cacheKeyFunction
     * @param  array  $options
     * @return void
     */
    public function __construct(callable $batchFunction, callable $cacheKeyFunction = null, array $options = [])
    {
        if (!isset($cacheKeyFunction)) {
            $cacheKeyFunction = function ($entity, $index) {
                return $entity->id ?? $entity['id'] ?? $index;
            };
        }

        $this->batchFunction = $batchFunction;
        $this->cacheKeyFunction = $cacheKeyFunction;
        $this->options = array_replace($this->options, $options);
    }

    /**
     * Add a batch of keys to the buffer.
     *
     * @param  mixed  $keys
     * @return self
     */
    public function batch($keys) : self
    {
        foreach ((array) $keys as $key) {
            $this->buffer($key);
        }

        return $this;
    }

    /**
     * Add a key to the buffer.
     *
     * @param  mixed  $key
     * @return self
     */
    public function buffer($key) : self
    {
        if (!is_integer($key) && !is_string($key)) {
            throw new InvalidArgumentException('Key must be an integer or string type, ' . gettype($key) . ' found.');
        }

        $this->buffer[$key] = true;

        return $this;
    }

    /**
     * Load a single entity form the cache by key or return null if not found.
     *
     * @param  mixed  $key
     * @return mixed|null
     */
    public function load($key)
    {
        $this->updateCache();

        return $this->loaded[$key] ?? null;
    }

    /**
     * Load multiple entities by their key and return an associative array with
     * entities indexed by their key.
     *
     * @param  array  $keys
     * @return array
     */
    public function loadMany(array $keys) : array
    {
        $this->updateCache();

        return array_intersect_key($this->loaded, array_flip($keys));
    }

    /**
     * Prime the cache with a preloaded entity.
     *
     * @param  mixed  $entity
     * @param  bool  $overwrite
     * @return self
     */
    public function prime($entity, bool $overwrite = false) : self
    {
        $key = call_user_func($this->cacheKeyFunction, $entity, null);

        if ($overwrite === false) {
            if (isset($this->loaded[$key])) {
                throw new RuntimeException('Entity with key ' . (string) $key . ' already exists in the cache.');
            }
        }

        $this->loaded[$key] = $entity;

        return $this;
    }

    /**
     * Remove all loaded entities from the cache.
     *
     * @return self
     */
    public function flush() : self
    {
        $this->loaded = [];

        return $this;
    }

    /**
     * Update the cache with the result of a batch query, and key entities using
     * the cache key function.
     *
     * @throws \UnexpectedValueException
     * @return void
     */
    protected function updateCache() : void
    {
        $queue = array_keys(array_diff_key($this->buffer, $this->loaded));

        while (!empty($queue)) {
            $batch = array_splice($queue, 0, $this->options['batch_size']);

            $loaded = call_user_func($this->batchFunction, $batch);

            if (!is_iterable($loaded)) {
                throw new UnexpectedValueException('Batch function must return an array or iterable, ' . gettype($loaded) . ' found.');
            }

            foreach ($loaded as $index => $entity) {
                $key = call_user_func($this->cacheKeyFunction, $entity, $index);

                $this->loaded[$key] = $entity;
            }
        }

        $this->buffer = [];
    }

    /**
     * Dump the contents of the buffer and cache. Primarily used for testing.
     *
     * @return array
     */
    public function dump() : array
    {
        return [
            'buffer' => $this->buffer,
            'cache' => $this->loaded,
        ];
    }
}
