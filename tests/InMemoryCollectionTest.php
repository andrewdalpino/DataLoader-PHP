<?php

namespace AndrewDalpino\DataLoader\tests;

use AndrewDalpino\DataLoader\InMemoryCollection;
use PHPUnit\Framework\TestCase;

class InMemoryCollectionTest extends TestCase
{
    public function test_collection_has_item()
    {
        $collection = TestCollection::mock(['aaaa' => 'foo', 'bbbb' => 'bar']);

        $this->assertTrue($collection->has('aaaa'));
        $this->assertFalse($collection->has('cccc'));
    }

    public function test_put_item_in_collection()
    {
        $collection = TestCollection::mock();

        $this->assertEquals(0, $collection->count());

        $collection->put('aaaa', 'data');

        $this->assertEquals(1, $collection->count());
        $this->assertEquals('data', $collection->get('aaaa'));
    }

    public function test_get_item_from_collection()
    {
        $collection = TestCollection::mock(['aaaa' => 'foo', 'bbbb' => 'bar']);

        $entity = $collection->get('bbbb');

        $this->assertEquals('bar', $entity);
    }

    public function test_get_all_items()
    {
        $collection = TestCollection::mock(['foo', 'bar', 'baz']);

        $this->assertEquals(['foo', 'bar', 'baz'], $collection->all());
    }

    public function test_count_items_in_collection()
    {
        $collection = TestCollection::mock([1, 2, 3, 4, 5]);

        $this->assertEquals(5, $collection->count());
    }

    public function test_key_items_by_callback()
    {
        $collection = TestCollection::mock([0 => ['id' => 3], 1 => ['id' => 2], 2 => ['id' => 'foo']]);

        $collection = $collection->keyBy(function ($item, $key) {
            return $item['id'];
        });

        $this->assertEquals([3, 2, 'foo'], $collection->keys());
    }

    public function test_get_all_keys()
    {
        $collection = TestCollection::mock(['heb' => 1, 'dub' => 2, 7 => 3, 'man' => 4, 'gil' => 5]);

        $this->assertEquals(['heb', 'dub', 7, 'man', 'gil'], $collection->keys());
    }

    public function test_flush_in_memory_collection()
    {
        $collection = TestCollection::mock(['aaaa' => 'data']);

        $this->assertEquals(1, $collection->count());

        $collection->flush();

        $this->assertEquals(0, $collection->count());
    }

    public function test_filter_cache_by_key()
    {
        $collection = TestCollection::mock([
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
        $collection = TestCollection::mock([
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
        $collection = TestCollection::mock(['aaaa' => 'foo']);

        $items = ['bbbb' => 'bar'];

        $collection->merge($items);

        $this->assertEquals(['aaaa' => 'foo', 'bbbb' => 'bar'], $collection->all());
    }

    public function test_take_items_from_collection()
    {
        $collection = TestCollection::mock([
            0 => 'first',
            1 => 'second',
            3 => 'third',
        ]);

        $taken = $collection->take(2);

        $this->assertContains('first', $taken);
        $this->assertContains('second', $taken);

        $this->assertEquals([3 => 'third'], $collection->all());
    }

    public function test_diff_keys()
    {
        $collection = TestCollection::mock([
            'aaaa' => 'foo',
            'bbbb' => 'bar',
        ]);

        $keys = [
            'aaaa' => null,
        ];

        $this->assertEquals(['bbbb' => 'bar'], $collection->diffKeys($keys)->all());
    }
}

class TestCollection extends InMemoryCollection
{
    public static function mock($items = null)
    {
        return new self($items);
    }
}
