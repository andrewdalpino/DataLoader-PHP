<?php

namespace AndrewDalpino\DataLoader\tests;

use AndrewDalpino\DataLoader\RequestCache;
use PHPUnit\Framework\TestCase;

class RequestCacheTest extends TestCase
{
    protected $loaded;

    public function __construct()
    {
        $this->loaded = RequestCache::init();
    }

    public function test_init_request_cache()
    {
        $this->assertTrue($this->loaded instanceof RequestCache);

        $this->assertEquals(0, $this->loaded->count());
    }
}
