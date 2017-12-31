<?php

namespace AndrewDalpino\DataLoader\tests;

use AndrewDalpino\DataLoader\BatchingDataLoader;
use PHPUnit\Framework\TestCase;

class BatchingDataLoaderTest extends TestCase
{
    protected $dataloader;

    public function setUp()
    {
        $data = [
            ['id' => 1, 'name' => 'Andrew'],
            ['id' => 2, 'name' => 'Frank'],
            ['id' => 3, 'name' => 'Ken'],
            ['id' => 4, 'name' => 'Julie'],
            ['id' => 'aaaa', 'name' => 'Rich'],
            ['id' => 'bbbb', 'name' => 'Saoirse'],
        ];

        $cacheKeyFunction = function ($entity, $index) {
            return $entity['id'];
        };

        // Simulate a database.
        $batchFunction = function ($keys) use ($data) {
            return array_filter($data, function ($entity, $key) use ($keys) {
                return in_array($entity['id'], $keys);
            }, ARRAY_FILTER_USE_BOTH);
        };

        $options = [
            'batch_size' => 3
        ];

        $this->dataloader = new BatchingDataLoader($batchFunction, $cacheKeyFunction, $options);
    }

    public function test_make_batching_dataloader()
    {
        $dataloader = BatchingDataLoader::make(function ($keys) {
            return null;
        });

        $this->assertTrue($dataloader instanceof BatchingDataLoader);
    }

    public function test_batch_keys()
    {
        $this->dataloader->batch(1);

        $this->assertEquals(1, $this->dataloader->buffer()->count());

        $this->dataloader->batch([1, 2, 3]);

        $this->assertEquals(4, $this->dataloader->buffer()->count());

        $keys = $this->dataloader->buffer()->deduplicate()->dump();

        $this->assertEquals([1, 2, 3], $keys);
    }

    public function test_load_single_entity()
    {
        $entity = $this->dataloader->batch(3)->load(3);

        $this->assertEquals(3, $entity['id']);
        $this->assertEquals('Ken', $entity['name']);
        $this->assertEquals(null, $this->dataloader->load(1));
    }

    public function test_load_multiple_entities()
    {
        $loaded = $this->dataloader->batch([1, 2, 'aaaa'])->loadMany([1, 2, 'aaaa', 'bbbb']);

        $this->assertEquals(3, count($loaded));
        $this->assertEquals('Andrew', $loaded[1]['name']);
        $this->assertEquals('Frank', $loaded[2]['name']);
        $this->assertEquals('Rich', $loaded['aaaa']['name']);
        $this->assertFalse(isset($loaded['bbbb']));
    }

    public function test_prime_cache()
    {
        $this->dataloader->prime(['id' => 6, 'name' => 'Francois']);

        $this->assertEquals(1, $this->dataloader->cache()->count());

        $entity = $this->dataloader->load(6);

        $this->assertEquals('Francois', $entity['name']);
    }
}
