<?php

namespace Tests\Unit;

use App\Lib\StorageBuffer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class StorageBufferTest extends TestCase
{
    protected $instance;
    protected $insertKey = 'buffer:insert';
    protected $deleteKey = 'buffer:delete';
    protected $modifiedAtKey = 'buffer:modified_at';

    function setUp(): void
    {
        putenv("BUFFER_PERSIST_TIMEOUT=60");

        $this->instance = new StorageBuffer();
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
        putenv("BUFFER_PERSIST_TIMEOUT=60");
        parent::tearDown();
    }


    function testDelete()
    {
        $this->instance->add(1, Carbon::now()->subSeconds(10));
        $this->instance->add(2, Carbon::now()->subSeconds(9));
        $this->instance->add(3, Carbon::now()->subSeconds(8));

        $ret = Redis::zRange($this->deleteKey, 0, -1);
        $this->assertEquals(0, count($ret), 'nothing should exist in queue now');

        $this->instance->delete(1, Carbon::now()->subSeconds(7));
        $ret = Redis::zRange($this->deleteKey, 0, -1);
        $this->assertEquals(1, count($ret), '1 item should be enqueued');
        $ret = Redis::zRange($this->insertKey, 0, -1);
        $this->assertEquals(2, count($ret), '2 items should remaining in the insert queue');

        $this->instance->delete(2, Carbon::now()->subSeconds(6));
        $ret = Redis::zRange($this->deleteKey, 0, -1);
        $this->assertEquals(2, count($ret), '2 items should be enqueued');
        $ret = Redis::zRange($this->insertKey, 0, -1);
        $this->assertEquals(1, count($ret), '1 item should remaining in the insert queue');
    }

    function testPersist()
    {
        $i = 1;
        $count = 25;
        while($i <= $count) {
            $time = $i < 13 ? Carbon::now()->subDay() : Carbon::now();
            $time->addSeconds($i);

            $this->instance->add($i, $time);
            $i++;
        }

        // delete 13 and 21
        $this->instance->delete(8, Carbon::now()->subDay());
        $this->instance->delete(21, Carbon::now());

        $this->instance->persist(function($ids) {
            $this->assertEquals(11, count($ids), 'There should be 11 ids returned for insert');
        }, function($ids) {
            $this->assertEquals(1, count($ids), 'There should be 1 ids returned for delete, as the other one is before the threshold');
        });

        $ret = Redis::zRange($this->insertKey, 0, -1);
        $this->assertEquals(12, count($ret), 'There should be 12 ids remaining for insert');
        $ret = Redis::zRange($this->deleteKey, 0, -1);
        $this->assertEquals(1, count($ret), 'There should be 1 ids remaining for delete');
    }
}
