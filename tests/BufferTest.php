<?php

namespace AndrewDalpino\DataLoader\tests;

use AndrewDalpino\DataLoader\Buffer;
use PHPUnit\Framework\TestCase;

class BufferTest extends TestCase
{
    protected $buffer;

    public function __construct()
    {
        $this->buffer = Buffer::init();
    }

    public function test_init_buffer()
    {
        $this->assertTrue($this->buffer instanceof Buffer);
        
        $this->assertEquals(0, $this->buffer->count());
    }
}
