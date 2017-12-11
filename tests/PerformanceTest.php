<?php

namespace AndrewDalpino\DataLoader\tests;

use AndrewDalpino\DataLoader\InMemoryCollection;
use PHPUnit\Framework\TestCase;

class PerformanceTest extends TestCase
{
    const READ_TARGET = 0.0005;
    const FILTER_TARGET = 0.005;

    public function test_multiple_insert_and_read_performance()
    {
        $collection = Collection::mock();

        $data = $this->generate_data(1000);

        $test = array_keys($data);

        shuffle($test);

        $test = array_slice($test, 0, 100);

        $start = microtime(true);

        foreach ($data as $key => $value) {
            $collection->put($key, $value);
        }

        foreach ($test as $key) {
            $collection->get($key);
        }

        $time = round(microtime(true) - $start, 8);

        echo "\n" . 'InMemoryCollection (Multiple Insert + Multiple Read) Performance: ' . $time . 's';

        $this->assertTrue($time <= self::READ_TARGET);
    }

    public function test_multiple_insert_and_filter_performance()
    {
        $collection = Collection::mock();

        $data = $this->generate_data(1000);

        $test = array_keys($data);

        shuffle($test);

        $test = array_slice($test, 0, 100);

        $start = microtime(true);

        foreach ($data as $key => $value) {
            $collection->put($key, $value);
        }

        $collection->filter(function ($key, $value) use ($test) {
            return in_array($key, $test);
        }, ARRAY_FILTER_USE_BOTH);

        $time = round(microtime(true) - $start, 8);

        echo "\n" . 'InMemoryCollection (Multiple Insert + Filter) Performance: ' . $time . 's';

        $this->assertTrue($time <= self::FILTER_TARGET);
    }

    public function generate_data(int $x = 1000, array $data = []) : array
    {
        for ($i = 0; $i < $x; $i++) {
            $data[uniqid()] = bin2hex(random_bytes(2048));
        }
        return $data;
    }
}

class Collection extends InMemoryCollection
{
    public static function mock()
    {
        return new self();
    }
}
