<?php

namespace AndrewDalpino\DataLoader\tests;

use AndrewDalpino\DataLoader\Buffer;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class BufferTest extends TestCase
{
    protected $buffer;

    public function setUp()
    {
        $keys = [1, 2, 3, 'aaaa', 'bbbb'];

        $this->buffer = new Buffer($keys);
    }

    public function test_init_buffer()
    {
        $buffer = Buffer::init();

        $this->assertTrue($buffer instanceof Buffer);
        $this->assertEquals(0, $buffer->count());
    }

    public function test_enqueue_integer_key()
    {
        $this->buffer->enqueue(4);

        $this->assertEquals(6, $this->buffer->count());
        $this->assertEquals(4, $this->buffer->last());
    }

    public function test_enqueue_string_key()
    {
        $this->buffer->enqueue('cccc');

        $this->assertEquals(6, $this->buffer->count());
        $this->assertEquals('cccc', $this->buffer->last());
    }

    public function test_enqueue_array_key()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->buffer->enqueue(['bad']);
    }

    public function test_enqueue_float_key()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->buffer->enqueue(3.14159);
    }

    public function test_dequeue_single_key()
    {
        $keys = $this->buffer->dequeue();

        $this->assertTrue(is_array($keys));
        $this->assertEquals(4, $this->buffer->count());
        $this->assertEquals(1, $keys[0]);
    }

    public function test_dequeue_multiple_keys()
    {
        $keys = $this->buffer->dequeue(6);

        $this->assertTrue(is_array($keys));
        $this->assertEquals(0, $this->buffer->count());
        $this->assertEquals(1, $keys[0]);
        $this->assertEquals(2, $keys[1]);
        $this->assertEquals(3, $keys[2]);
        $this->assertEquals('aaaa', $keys[3]);
        $this->assertEquals('bbbb', $keys[4]);
        $this->assertFalse(isset($keys[5]));
    }

    public function test_deduplicate_keys()
    {
        $this->buffer->enqueue(1);
        $this->buffer->enqueue('aaaa');
        $this->buffer->enqueue('dddd');

        $this->assertEquals(8, $this->buffer->count());

        $buffer = $this->buffer->deduplicate();

        $this->assertEquals(6, $buffer->count());
    }

    public function test_diff_keys()
    {
        $keys = [1, 2, 'aaaa', 'zzzz'];

        $buffer = $this->buffer->diff($keys);

        $this->assertEquals(2, $buffer->count());

        $dump = $buffer->dump();

        $this->assertEquals(3, $dump[0]);
        $this->assertEquals('bbbb', $dump[1]);
    }

    public function test_last_key()
    {
        $this->buffer->enqueue('foo');

        $this->assertEquals('foo', $this->buffer->last());
    }

    public function test_count_keys()
    {
        $this->assertEquals(5, $this->buffer->count());
    }

    public function test_flush_buffer()
    {
        $this->buffer->flush();

        $this->assertEquals(0, $this->buffer->count());
    }

    public function test_dump_buffer()
    {
        $keys = $this->buffer->dump();

        $this->assertTrue(is_array($keys));
        $this->assertEquals(5, $this->buffer->count());
        $this->assertEquals(1, $keys[0]);
        $this->assertEquals(2, $keys[1]);
        $this->assertEquals(3, $keys[2]);
        $this->assertEquals('aaaa', $keys[3]);
        $this->assertEquals('bbbb', $keys[4]);

    }
}
