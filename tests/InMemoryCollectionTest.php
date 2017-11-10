<?php

namespace AndrewDalpino\DataLoader\tests;

use AndrewDalpino\DataLoader\InMemoryCollection;
use PHPUnit\Framework\TestCase;

class InMemoryCollectionTest extends TestCase
{
    public function test_collection_has_item()
    {
        $collection = new InMemoryCollection(['aaaa' => 'foo', 'bbbb' => 'bar']);

        $this->assertTrue($collection->has('aaaa'));
        $this->assertFalse($collection->has('cccc'));
    }

    public function test_put_item_in_collection()
    {
        $collection = new InMemoryCollection();

        $this->assertEquals(0, $collection->count());

        $collection->put('aaaa', 'data');

        $this->assertEquals(1, $collection->count());
        $this->assertEquals('data', $collection->get('aaaa'));
    }

    public function test_get_item_from_collection()
    {
        $collection = new InMemoryCollection(['aaaa' => 'foo', 'bbbb' => 'bar']);

        $entity = $collection->get('bbbb');

        $this->assertEquals('bar', $entity);
    }

    public function test_get_all_items()
    {
        $collection = new InMemoryCollection(['foo', 'bar', 'baz']);

        $this->assertEquals(['foo', 'bar', 'baz'], $collection->all());
    }

    public function test_count_items_in_collection()
    {
        $collection = new InMemoryCollection([1, 2, 3, 4, 5]);

        $this->assertEquals(5, $collection->count());
    }

    public function test_key_items_by_callback()
    {
        $collection = new InMemoryCollection([0 => ['id' => 3], 1 => ['id' => 2], 2 => ['id' => 'foo']]);

        $collection = $collection->keyBy(function ($item, $key) {
            return $item['id'];
        });

        $this->assertEquals([3, 2, 'foo'], $collection->keys());
    }

    public function test_get_all_keys()
    {
        $collection = new InMemoryCollection(['heb' => 1, 'dub' => 2, 7 => 3, 'man' => 4, 'gil' => 5]);

        $this->assertEquals(['heb', 'dub', 7, 'man', 'gil'], $collection->keys());
    }

    public function test_flush_in_memory_collection()
    {
        $collection = new InMemoryCollection(['aaaa' => 'data']);

        $this->assertEquals(1, $collection->count());

        $collection->flush();

        $this->assertEquals(0, $collection->count());
    }

    public function test_filter_cache_by_key()
    {
        $collection = new InMemoryCollection([
            'aaaa' => 'foo',
            'bbbb' => 'bar',
        ]);

        $collection = $collection->filter(function ($value, $key) {
            return $key === 'aaaa';
        });

        $this->assertEquals(['aaaa' => 'foo'], $collection->all());
    }

    public function test_filter_cache_by_value()
    {
        $collection = new InMemoryCollection([
            'aaaa' => 'foo',
            'bbbb' => 'bar',
        ]);

        $collection = $collection->filter(function ($value, $key) {
            return $value === 'bar';
        });

        $this->assertEquals(['bbbb' => 'bar'], $collection->all());
    }

    public function test_merge_items_with_collection()
    {
        $collection = new InMemoryCollection(['aaaa' => 'foo']);

        $items = ['bbbb' => 'bar'];

        $collection->merge($items);

        $this->assertEquals(['aaaa' => 'foo', 'bbbb' => 'bar'], $collection->all());
    }

    public function test_diff_keys()
    {
        $collection = new InMemoryCollection([
            'aaaa' => 'foo',
            'bbbb' => 'bar',
        ]);

        $keys = [
            'aaaa' => null,
        ];

        $this->assertEquals(['bbbb' => 'bar'], $collection->diffKeys($keys)->all());
    }
}
