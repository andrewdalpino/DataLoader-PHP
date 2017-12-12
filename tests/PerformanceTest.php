<?php

namespace AndrewDalpino\DataLoader\tests;

use AndrewDalpino\DataLoader\InMemoryCollection;
use PHPUnit\Framework\TestCase;

class PerformanceTest extends TestCase
{
    const READ_TARGET = 0.0005; // seconds
    const READ_MULTIPLE_TARGET = 0.0005; // seconds
    const DATASET_SIZE = 1000;
    const TEST_SIZE = 1000;
    const ENTITY_BYTES = 2048;

    public function test_single_insert_and_single_read_performance()
    {
        $collection = Collection::mock();

        $data = $this->generate_data(self::DATASET_SIZE);

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

        echo "\n" . 'InMemoryCollection (Single Insert + Single Read) Performance: ' . $time . 's';

        $this->assertTrue($time <= self::READ_TARGET);
    }

    public function test_single_insert_and_multiple_read_performance()
    {
        $collection = Collection::mock();

        $data = $this->generate_data(self::DATASET_SIZE);

        $test = array_keys($data);

        shuffle($test);

        $test = array_slice($test, 0, 100);

        $start = microtime(true);

        foreach ($data as $key => $value) {
            $collection->put($key, $value);
        }

        $collection->getMany($test);

        $time = round(microtime(true) - $start, 8);

        echo "\n" . 'InMemoryCollection (Single Insert + Multiple Read) Performance: ' . $time . 's';

        $this->assertTrue($time <= self::READ_MULTIPLE_TARGET);
    }

    public function generate_data(int $x = 1000, array $data = []) : array
    {
        for ($i = 0; $i < $x; $i++) {
            $data[uniqid()] = bin2hex(random_bytes(self::ENTITY_BYTES));
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
