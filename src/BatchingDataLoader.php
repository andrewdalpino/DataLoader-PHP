<?php

namespace AndrewDalpino\DataLoader;

use InvalidArgumentException;

class BatchingDataLoader
{
    /**
     * The entities to buffer.
     *
     * @var  \AndrewDalpino\DataLoader\Buffer  $buffer
     */
    protected $buffer;

    /**
     * The current request cycle cache.
     *
     * @var  \AndrewDalpino\DataLoader\RequestCache  $loaded
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
    protected $options;

    /**
     * Factory build method.
     *
     * @param  callable  $batchFunction
     * @param  callable|null  $cacheKeyFunction
     * @param  array  $options
     * @return self
     */
    public static function make(callable $batchFunction, callable $cacheKeyFunction = null, array $options = [])
    {
        if (is_null($cacheKeyFunction)) {
            $cacheKeyFunction = function ($entity, $index) {
                return $entity->id ?? $entity['id'] ?? $index;
            };
        }

        $options = array_merge([
            'batch_size' => 1000
        ], $options);

        return new self($batchFunction, $cacheKeyFunction, $options);
    }

    /**
     * @param  callable  $batchFunction
     * @param  callable  $cacheKeyFunction
     * @param  array  $options
     * @return void
     */
    protected function __construct(callable $batchFunction, callable $cacheKeyFunction, array $options)
    {
        $this->batchFunction = $batchFunction;
        $this->cacheKeyFunction = $cacheKeyFunction;
        $this->options = $options;
        $this->buffer = Buffer::init();
        $this->loaded = RequestCache::init();
    }

    /**
     * Add keys to the buffer.
     *
     * @param  mixed  $keys
     * @return self
     */
    public function batch($keys)
    {
        foreach ((array) $keys as $index => $key) {
            $key = $this->convertToCacheKey($key);

            $this->buffer->put($key, null);
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
        if (is_array($key)) {
            return $this->loadMany($key);
        }

        $key = $this->convertToCacheKey($key);

        return $this->execute()->get($key, null);
    }

    /**
     * Load multiple entities by their key.
     *
     * @param  array  $keys
     * @return array
     */
    public function loadMany(array $keys) : array
    {
        $keys = array_map(function ($key) {
            return $this->convertToCacheKey($key);
        }, $keys);

        return $this->execute()->filter(function ($value, $key) use ($keys) {
            return in_array($key, $keys);
        })->all();
    }

    /**
     * Prime the request cache.
     *
     * @param  mixed  $entities
     * @return self
     */
    public function prime($entities)
    {
        if (! is_iterable($entities)) {
            $entities = [$entities];
        }

        foreach ($entities as $index => $entity) {
            $key = call_user_func($this->cacheKeyFunction, $entity, $index);

            $key = $this->convertToCacheKey($key);

            if (! $this->loaded->has($key)) {
                $this->loaded->put($key, $entity);
            }
        }

        return $this;
    }

    /**
     * Forget a single entity stored in the request cache.
     *
     * @param  mixed  $key
     * @return self
     */
    public function forget($key)
    {
        $key = $this->convertToCacheKey($key);

        $this->loaded->forget($key);

        return $this;
    }

    /**
     * Flush the entire request cache.
     *
     * @return self
     */
    public function flush()
    {
        $this->loaded->flush();

        return $this;
    }

    /**
     * Load all buffered entities that aren't already in the request cache.
     *
     * @throws \AndrewDalpino\DataLoader\InvalidArgumentException
     * @return \AndrewDalpino\DataLoader\RequestCache
     */
    protected function execute() : RequestCache
    {
        $queue = $this->buffer->diffKeys($this->loaded);

        while ($queue->count() > 0) {
            $batch = $queue->take($this->options['batch_size'], true);

            $batch = $batch->keyBy(function ($entity, $key) {
                return $this->convertToStorageKey($key);
            });

            $loaded = call_user_func($this->batchFunction, $batch->keys());

            if (! is_iterable($loaded)) {
                throw new InvalidArgumentException('Batch function must return an array or iterable object, '
                    . gettype($loaded) . ' found instead.');
            }

            $results = new ResultSet($loaded);

            $results = $results->keyBy(function ($entity, $index) {
                $key = call_user_func($this->cacheKeyFunction, $entity, $index);

                return $this->convertToCacheKey($key);
            });

            $this->loaded->merge($results);
        }

        $this->buffer->flush();

        return $this->loaded;
    }

    /**
     * Convert the given key to a cache safe key.
     *
     * @param  mixed  $key
     * @return string
     */
    protected function convertToCacheKey($key) : string
    {
        $this->checkKey($key);

        return (string) ':' . $key;
    }

    /**
     * Convert the cache key back to a storage key.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function convertToStorageKey(string $key)
    {
        $key = substr(strstr($key, ':'), 1);

        return is_numeric($key) ? (int) $key : $key;
    }

    /**
     * Validate the key.
     *
     * @param  mixed  $key
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function checkKey($key) : void
    {
        if (! is_int($key) && ! is_string($key)) {
            throw new InvalidArgumentException('Key must be an integer or string type, '
                . gettype($key) . ' found instead.');
        }
    }
}
