<?php

namespace Tests\Unit;

use App\Feed;
use App\Lib\FeedSubscriber\SubscribeToAll;
use App\Lib\StorageBuffer;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        Redis::del($this->insertKey);
        Redis::del($this->deleteKey);
        Redis::del($this->modifiedAtKey);
        putenv("BUFFER_PERSIST_TIMEOUT=60");
        parent::tearDown();
    }


    function testDelete()
    {

        $this->instance->add(1, 1);
        $this->instance->add(2, 2);
        $this->instance->add(3, 3);

        $ret = Redis::zRange($this->deleteKey, 0, -1);
        $this->assertEquals(0, count($ret), 'nothing should exist in queue now');

        $this->instance->delete(1, 1);
        $ret = Redis::zRange($this->deleteKey, 0, -1);
        $this->assertEquals(1, count($ret), '1 item should be enqueued');
        $ret = Redis::zRange($this->insertKey, 0, -1);
        $this->assertEquals(2, count($ret), '2 items should remaining in the insert queue');

        $this->instance->delete(2, 2);
        $ret = Redis::zRange($this->deleteKey, 0, -1);
        $this->assertEquals(2, count($ret), '2 items should be enqueued');
        $ret = Redis::zRange($this->insertKey, 0, -1);
        $this->assertEquals(1, count($ret), '1 item should remaining in the insert queue');
    }

    function testGetWithNoDelete()
    {
        $i = 1;
        $count = 25;
        while($i <= $count) {
            $this->instance->add($i, $i);
            $i++;
        }

        $pageSize = 10;

        $ret = $this->instance->get(0, $pageSize);
        $this->assertEquals(16, end($ret), 'first page last element should be 16');

        $ret = $this->instance->get(10, $pageSize);
        $this->assertEquals(6, end($ret), 'second page last element should be 6');

        $ret = $this->instance->get(20, $pageSize);
        $this->assertEquals(1, end($ret), 'third page last element should be 1');
        $this->assertEquals(5, count($ret), 'third page should have 5 remaining items');
    }

    function testGetWithDelete()
    {
        $i = 1;
        $count = 25;
        while($i <= $count) {
            $this->instance->add($i, $i);
            $i++;
        }

        // delete 13 and 21
        $this->instance->delete(13, 13);
        $this->instance->delete(21, 21);

        $pageSize = 10;

        $ret = $this->instance->get(0, $pageSize);
        $this->assertEquals(15, end($ret), 'first page last element should be 15, (skipping 21)');

        $ret = $this->instance->get(10, $pageSize);
        $this->assertEquals(4, end($ret), 'second page last element should be 4, (skipping 13)');

        $ret = $this->instance->get(20, $pageSize);
        $this->assertEquals(1, end($ret), 'third page last element should be 1');
        $this->assertEquals(3, count($ret), 'third page should have 3 remaining items');
    }

    function testPersist()
    {
        $before = Carbon::now()->subDay()->timestamp;
        $after = Carbon::now()->timestamp;

        $i = 1;
        $count = 25;
        while($i <= $count) {
            $ts = $i < 13 ? $before : $after;

            $this->instance->add($i, $ts);
            $i++;
        }

        $this->instance->persist(function($ids) {
            $this->assertEquals(12, count($ids), 'There should be 12 ids returned');
        });
    }
}
