<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class HelperTest extends TestCase
{
    protected $key;

    function setUp(): void
    {
        putenv("BUFFER_PERSIST_TIMEOUT=60");

        $this->key = 'whatever';
        parent::setUp();
    }

    function tearDown(): void
    {
        // get rid of everything
        $prefix = Config::get('database.redis.options.prefix');
        $keys = Redis::keys('*');
        foreach($keys as $key) {
            Redis::del(str_replace($prefix, '', $key));
        }
        parent::tearDown();
    }

    function testGetTimeSeriesWithNoDelete()
    {
        $now = Carbon::now();
        $i = 1;
        $count = 25;
        while($i <= $count) {
            // starting for 10 seconds ago and increment by 1 second
            Redis::zAdd($this->key, Carbon::now()->subSeconds($count + 10)->addSeconds($i)->getPreciseTimestamp(3), $i);
            $i++;
        }

        $limit = 10;

        // first page
        $ret = get_timeseries($this->key, $now, $limit);
        $this->assertEquals(16, $ret->uuids()->last(), 'first page last element should be 16');

        // second page
        $from = $ret->timeTo();
        $ret = get_timeseries($this->key, $from, $limit);
        $this->assertEquals(6, $ret->uuids()->last(), 'second page last element should be 6');

        // third page
        $from = $ret->timeTo();
        $ret = get_timeseries($this->key, $from, $limit);
        $this->assertEquals(1, $ret->uuids()->last(), 'third page last element should be 1');
        $this->assertEquals(5, $ret->count(), 'third page should have 5 remaining items');
    }

    function testGetTimeSeriesWithDelete()
    {
        $i = 1;
        $count = 25;
        while($i <= $count) {
            // starting for 10 seconds ago and increment by 1 second
            Redis::zAdd($this->key, Carbon::now()->subSeconds($count + 10)->addSeconds($i)->getPreciseTimestamp(3), $i);
            $i++;
        }

        $limit = 10;

        // delete 13 and 21
        Redis::zRem($this->key, 13);
        Redis::zRem($this->key, 21);

        // first page
        $ret = get_timeseries($this->key, Carbon::now(), $limit);
        $this->assertEquals(15, $ret->uuids()->last(), 'first page last element should be 15, (skipping 21)');

        // second page
        $from = $ret->timeTo();
        $ret = get_timeseries($this->key, $from, $limit);
        $this->assertEquals(4, $ret->uuids()->last(), 'second page last element should be 4, (skipping 13)');

        // third page
        $from = $ret->timeTo();
        $ret = get_timeseries($this->key, $from, $limit);
        $this->assertEquals(1, $ret->uuids()->last(), 'third page last element should be 1');
        $this->assertEquals(3, count($ret), 'third page should have 3 remaining items');
    }
}
