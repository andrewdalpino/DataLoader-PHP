<?php

namespace AndrewDalpino\DataLoader\tests;

use AndrewDalpino\DataLoader\BatchingDataLoader;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use UnexpectedValueException;
use RuntimeException;

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
            ['id' => 'a', 'name' => 'Rich'],
            ['id' => 'b', 'name' => 'Saoirse'],
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

    public function test_build_batching_dataloader()
    {
        $this->assertTrue($this->dataloader instanceof BatchingDataLoader);
    }

    public function test_bad_batch_function()
    {
        $dataloader = new BatchingDataLoader(function ($keys) {
            return 'Bad';
        });

        $this->expectException(UnexpectedValueException::class);

        $dataloader->batch('a')->load('a');
    }

    public function test_batch_a_single_key()
    {
        $this->dataloader->batch(1);

        $this->assertEquals(1, count($this->dataloader->dump()['buffer']));
        $this->assertEquals([1 => true], $this->dataloader->dump()['buffer']);
    }

    public function test_batch_multiple_keys()
    {
        $this->dataloader->batch([1, 2, 3]);

        $this->assertEquals(3, count($this->dataloader->dump()['buffer']));
        $this->assertEquals([1 => true, 2 => true, 3 => true], $this->dataloader->dump()['buffer']);
    }

    public function test_batch_bad_key()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->dataloader->batch([1, 3.14159, 'a']);
    }

    public function test_load_single_entity()
    {
        $entity = $this->dataloader->batch(3)->load(3);

        $this->assertEquals(3, $entity['id']);
        $this->assertEquals('Ken', $entity['name']);
    }

    public function test_load_unbuffered_entity()
    {
        $entity = $this->dataloader->batch(1)->load('a');

        $this->assertNull($entity);
    }

    public function test_load_multiple_entities()
    {
        $loaded = $this->dataloader->batch([1, 2, 'a'])->loadMany([1, 2, 'a', 'b']);

        $this->assertEquals(3, count($loaded));
        $this->assertEquals('Andrew', $loaded[1]['name']);
        $this->assertEquals('Frank', $loaded[2]['name']);
        $this->assertEquals('Rich', $loaded['a']['name']);
        $this->assertFalse(isset($loaded['b']));
        $this->assertFalse(isset($loaded['c']));
    }

    public function test_prime_cache()
    {
        $this->dataloader->prime(['id' => 6, 'name' => 'Francois']);

        $this->assertEquals(1, count($this->dataloader->dump()['cache']));

        $entity = $this->dataloader->load(6);

        $this->assertEquals('Francois', $entity['name']);
    }

    public function test_prime_previously_loaded_entity()
    {
        $this->dataloader->batch(1)->load(1);

        $this->expectException(RuntimeException::class);

        $this->dataloader->prime(['id' => 1, 'name' => 'Not Andrew'], false);
    }

    public function test_prime_previously_loaded_entity_w_overwrite()
    {
        $this->dataloader->batch(1)->load(1);

        $this->dataloader->prime(['id' => 1, 'name' => 'Not Andrew'], true);

        $entity = $this->dataloader->load(1);

        $this->assertEquals(1, $entity['id']);
        $this->assertEquals('Not Andrew', $entity['name']);
    }

    public function test_flush_cache()
    {
        $this->assertEquals(0, count($this->dataloader->dump()['cache']));

        $this->dataloader->batch([1, 2, 3])->loadMany([1, 2, 3]);

        $this->assertEquals(3, count($this->dataloader->dump()['cache']));

        $this->dataloader->flush();

        $this->assertEquals(0, count($this->dataloader->dump()['cache']));
    }
}
