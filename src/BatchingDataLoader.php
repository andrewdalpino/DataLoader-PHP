<?php

namespace AndrewDalpino\DataLoader;

use UnexpectedValueException;

class BatchingDataLoader
{
    /**
     * The key buffer.
     *
     * @var  \AndrewDalpino\DataLoader\Buffer  $buffer
     */
    protected $buffer;

    /**
     * The request cache.
     *
     * @var  \AndrewDalpino\DataLoader\Cache  $loaded
     */
    protected $loaded;

    /**
     * The anonymous function used to batch load entities.
     *
     * @var  callable  $batchFunction
     */
    protected $batchFunction;

    /**
     * The anonymous function used to return the cache key of an entity.
     *
     * @var  callable  $cacheKeyFunction
     */
    protected $cacheKeyFunction;

    /**
     * An array of options.
     *
     * @var  array  $options
     */
    protected $options = [
        'batch_size' => 1000,
    ];

    /**
     * Get the buffer instance.
     *
     * @return \AndrewDalpino\DataLoader\Buffer
     */
    public function buffer() : Buffer
    {
        return $this->buffer;
    }

    /**
     * Get the cache instance.
     *
     * @return \AndrewDalpino\DataLoader\Cache
     */
    public function cache() : Cache
    {
        return $this->loaded;
    }

    /**
     * Factory method.
     *
     * @param  callable  $batchFunction
     * @param  callable|null  $cacheKeyFunction
     * @param  array  $options
     * @return self
     */
    public static function make(callable $batchFunction, callable $cacheKeyFunction = null, array $options = []) : BatchingDataLoader
    {
        if (is_null($cacheKeyFunction)) {
            $cacheKeyFunction = function ($entity, $index) {
                return $entity->id ?? $entity['id'] ?? $index;
            };
        }

        return new self($batchFunction, $cacheKeyFunction, $options);
    }

    /**
     * @param  callable  $batchFunction
     * @param  callable  $cacheKeyFunction
     * @param  array  $options
     * @return void
     */
    public function __construct(callable $batchFunction, callable $cacheKeyFunction, array $options)
    {
        $this->buffer = Buffer::init();
        $this->loaded = Cache::init();
        $this->batchFunction = $batchFunction;
        $this->cacheKeyFunction = $cacheKeyFunction;
        $this->options = array_replace($this->options, $options);
    }

    /**
     * Add keys to the buffer.
     *
     * @param  mixed  $keys
     * @return self
     */
    public function batch($keys) : BatchingDataLoader
    {
        foreach ((array) $keys as $key) {
            $this->buffer()->enqueue($key);
        }

        return $this;
    }

    /**
     * Load a single entity or multiple entities by key.
     *
     * @param  mixed  $key
     * @return mixed|null
     */
    public function load($key)
    {
        return $this->updateCache()->cache()->get($key);
    }

    /**
     * Load multiple entities by their key.
     *
     * @param  array  $keys
     * @return array
     */
    public function loadMany(array $keys) : array
    {
        return $this->updateCache()->cache()->mget($keys);
    }

    /**
     * Prime the cache with a preloaded entity.
     *
     * @param  mixed  $entity
     * @return self
     */
    public function prime($entity) : BatchingDataLoader
    {
        $key = call_user_func($this->cacheKeyFunction, $entity, null);

        if (! $this->cache()->has($key)) {
            $this->cache()->put($key, $entity);
        }

        return $this;
    }

    /**
     * Fetch all buffered entities that aren't already in the cache.
     *
     * @throws \UnexpectedValueException
     * @return self
     */
    protected function updateCache() : BatchingDataLoader
    {
        $queue = $this->buffer()->deduplicate()->diff($this->cache()->keys());

        while ($queue->count()) {
            $batch = $queue->dequeue($this->options['batch_size']);

            $loaded = call_user_func($this->batchFunction, $batch);

            if (! is_iterable($loaded)) {
                throw new UnexpectedValueException('Batch function must return an array or iterable, ' . gettype($loaded) . ' found.');
            }

            foreach ($loaded as $index => $entity) {
                $key = call_user_func($this->cacheKeyFunction, $entity, $index);

                $this->cache()->put($key, $entity);
            }
        }

        $this->buffer()->flush();

        return $this;
    }
}
