<?php

namespace AndrewDalpino\DataLoader\tests;

use AndrewDalpino\DataLoader\Cache;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class CacheTest extends TestCase
{
    protected $cache;

    public function setUp()
    {
        $data = [
            1 => ['id' => 1, 'color' => 'Blue'],
            2 => ['id' => 2, 'color' => 'Brown'],
            3 => ['id' => 3, 'color' => 'Orange'],
            'aaaa' => ['id' => 'aaaa', 'color' => 'Red'],
            'bbbb' => ['id' => 'bbbb', 'color' => 'Black'],
        ];

        $this->cache = new Cache($data);
    }

    public function test_init_cache()
    {
        $cache = Cache::init();

        $this->assertTrue($cache instanceof Cache);
        $this->assertEquals(0, $cache->count());
    }

    public function test_cache_has_item()
    {
        $this->assertTrue($this->cache->has(1));
        $this->assertTrue($this->cache->has('aaaa'));
        $this->assertFalse($this->cache->has('zzzz'));
    }

    public function test_put_item_in_cache()
    {
        $this->cache->put(4, ['id' => 4, 'color' => 'Green']);

        $this->assertEquals(6, $this->cache->count());
        $this->assertTrue($this->cache->has(4));
    }

    public function test_put_bad_key_in_cache()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache->put(['bad'], ['color' => 'Black']);
    }

    public function test_get_item_from_cache()
    {
        $item = $this->cache->get(2);

        $this->assertEquals('Brown', $item['color']);
    }

    public function test_get_multiple_items_from_cache()
    {
        $items = $this->cache->mget([1, 2, 'aaaa', 'zzzz']);

        $this->assertEquals('Blue', $items[1]['color']);
        $this->assertEquals('Brown', $items[2]['color']);
        $this->assertEquals('Red', $items['aaaa']['color']);
        $this->assertEquals(null, isset($items['zzzz']));
    }

    public function test_merge_items_into_cache()
    {
        $items = [
            5 => 'foo',
            'cccc' => 'bar',
            1 => 'overwritten',
        ];

        $this->cache->merge($items);

        $this->assertEquals(7, $this->cache->count());

        $items = $this->cache->all();

        $this->assertEquals('foo', $items[5]);
        $this->assertEquals('bar', $items['cccc']);
        $this->assertEquals('overwritten', $items[1]);
        $this->assertEquals('Brown', $items[2]['color']);
    }

    public function test_get_cache_keys()
    {
        $keys = $this->cache->keys();

        $this->assertEquals(5, count($keys));
        $this->assertEquals(1, $keys[0]);
        $this->assertEquals(2, $keys[1]);
        $this->assertEquals(3, $keys[2]);
        $this->assertEquals('aaaa', $keys[3]);
        $this->assertEquals('bbbb', $keys[4]);
        $this->assertFalse(isset($keys[5]));
    }

    public function test_count_items()
    {
        $this->assertEquals(5, $this->cache->count());
    }

    public function test_forget_item()
    {
        $this->assertEquals(5, $this->cache->count());
        $this->assertEquals('Black', $this->cache->get('bbbb')['color']);

        $this->cache->forget('bbbb');

        $this->assertEquals(4, $this->cache->count());
        $this->assertEquals(null, $this->cache->get('bbbb'));
    }

    public function test_flush_cache()
    {
        $this->assertEquals(5, $this->cache->count());

        $this->cache->flush();

        $this->assertEquals(0, $this->cache->count());
    }
}
