<?php

namespace AndrewDalpino\DataLoader\tests;

use AndrewDalpino\DataLoader\BatchingDataLoader;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class BatchingDataLoaderTest extends TestCase
{
    protected $dataloader;

    public function __construct()
    {
        $data = [
            0 => ['id' => 1, 'name' => 'foo'],
            1 => ['id' => 2, 'name' => 'bar'],
            2 => ['id' => 3, 'name' => 'baz'],
            3 => ['id' => '00000000-0000-0000-0000-000000000001', 'name' => 'andrew'],
            4 => ['id' => 'some:thing', 'name' => ''],
            5 => ['id' => 5.5, 'name' => 'rhu barb'],
        ];

        $cacheKeyFunction = function ($entity, $key) {
            return $entity['id'];
        };

        $batchFunction = function ($keys) use ($data) {
            return array_filter($data, function ($entity, $key) use ($keys) {
                return in_array($entity['id'], $keys);
            }, ARRAY_FILTER_USE_BOTH);
        };

        $options = [
            'batch_size' => 3
        ];

        $this->dataloader = BatchingDataLoader::make($batchFunction, $cacheKeyFunction);
    }

    public function test_build_data_loader()
    {
        $this->assertTrue($this->dataloader instanceof BatchingDataLoader);
    }

    public function test_batch_keys_and_load_entities()
    {
        $this->dataloader->batch(1);
        $this->dataloader->batch([2, 3, '00000000-0000-0000-0000-000000000001', 'some:thing']);

        $entity = $this->dataloader->load(2);
        $many = $this->dataloader->load([1, 3, '00000000-0000-0000-0000-000000000001', 'some:thing']);

        $this->assertEquals(['id' => 2, 'name' => 'bar'], $entity);
        $this->assertEquals([
            ':1' => ['id' => 1, 'name' => 'foo'],
            ':3' => ['id' => 3, 'name' => 'baz'],
            ':00000000-0000-0000-0000-000000000001' => ['id' => '00000000-0000-0000-0000-000000000001', 'name' => 'andrew'],
            ':some:thing' => ['id' => 'some:thing', 'name' => ''],
        ], $many);
        $this->assertFalse(in_array(['id' => 2, 'name' => 'bar'], $many));

        $this->dataloader->flush();
    }

    public function test_prime_cache()
    {
        $this->dataloader->prime([0 => ['id' => 'foo', 'name' => 'donny']]);

        $entity = $this->dataloader->load('foo');

        $this->assertEquals(['id' => 'foo', 'name' => 'donny'], $entity);

        $this->dataloader->prime([0 => ['id' => 'foo', 'name' => 'sue']]);

        $entity = $this->dataloader->load('foo');

        $this->assertEquals(['id' => 'foo', 'name' => 'donny'], $entity);

        $this->dataloader->flush();
    }

    public function test_forget_cache_item()
    {
        $this->dataloader->batch(2);

        $entity = $this->dataloader->load(2);

        $this->assertTrue(! is_null($entity));

        $this->dataloader->forget(2);

        $entity = $this->dataloader->load(2);

        $this->assertTrue(is_null($entity));

        $this->dataloader->flush();
    }

    public function test_flush_cache()
    {
        $this->dataloader->batch([1, 2, 3]);

        $entities = $this->dataloader->load([1, 2, 3]);

        $this->assertEquals(3, count($entities));

        $this->dataloader->flush();

        $entities = $this->dataloader->load([1, 2, 3]);

        $this->assertEquals(0, count($entities));

        $this->dataloader->flush();
    }

    public function test_load_entity_from_cold_cache()
    {
        $entity = $this->dataloader->load(1);

        $this->assertTrue(is_null($entity));
    }

    public function test_bad_cache_key()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->dataloader->batch([5.5]);
    }

    public function test_bad_batch_function()
    {
        $dataloader = BatchingDataLoader::make(function ($keys) {
            return 'bad';
        });

        $dataloader->batch(['00000000-0000-0000-0000-000000000001']);

        $this->expectException(InvalidArgumentException::class);

        $entity = $dataloader->load('00000000-0000-0000-0000-000000000001');
    }
}
