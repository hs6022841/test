<?php

namespace Tests\Unit;

use App\Feed;
use App\Lib\TimeSeriesCollection;
use App\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TimeSeriesCollectionTest extends TestCase
{
    use RefreshDatabase;
    private $feedsInDb;
    private $feedsInRedis;

    function setUp(): void
    {
        parent::setUp();

        $user1 = factory(User::class)->create([
            'id' => 1
        ]);

        $this->feedsInDb = factory(Feed::class, 5)->create([
            'user_id' => $user1->id
        ]);

        $feeds = factory(Feed::class, 5)->make([
            'user_id' => $user1->id
        ]);
        foreach($feeds as $feed) {
            Redis::zAdd('test', $feed->created_at->getPreciseTimestamp(3), $feed->uuid);
        }

        $this->feedsInRedis =  Redis::zRange('test', 0 , -1, true);
    }

    function tearDown(): void
    {
        // get rid of everything
        $prefix = Config::get('database.redis.options.prefix');
        $keys = Redis::keys('*');
        foreach($keys as $key) {
            Redis::del(str_replace($prefix, '', $key));
        }
        // get rid of everything
        parent::tearDown();
    }


    public function testDbFeed()
    {
        $collection = new TimeSeriesCollection($this->feedsInDb);

        $this->assertEquals($this->feedsInDb[0]->uuid, $collection->uuids()->first(), 'first item should have a matching uuid');
        $this->assertEquals($this->feedsInDb[0]->created_at->timestamp, $collection->timeFrom()->timestamp, 'time from should match with first item');
        $this->assertEquals($this->feedsInDb[0]->created_at->getPreciseTimestamp(3), $collection->first(), 'first item\'s time should match');

        $this->assertEquals($this->feedsInDb[4]->uuid, $collection->uuids()->last(), 'last item should have a matching uuid');
        $this->assertEquals($this->feedsInDb[4]->created_at->timestamp, $collection->timeTo()->timestamp, 'time to should match with first item');
        $this->assertEquals($this->feedsInDb[4]->created_at->getPreciseTimestamp(3), $collection->last(), 'last item\'s time should match');

    }

    public function testRedisFeed()
    {
        $collection = new TimeSeriesCollection($this->feedsInRedis);

        $this->assertEquals(array_key_first($this->feedsInRedis), $collection->uuids()->first(), 'first item should have a matching uuid');
        $this->assertEquals($this->feedsInRedis[array_key_first($this->feedsInRedis)], $collection->timeFrom()->getPreciseTimestamp(3), 'time from should match with first item');
        $this->assertEquals($this->feedsInRedis[array_key_first($this->feedsInRedis)], $collection->first(), 'first item\'s time should match');

        $this->assertEquals(array_key_last($this->feedsInRedis), $collection->uuids()->last(), 'last item should have a matching uuid');
        $this->assertEquals($this->feedsInRedis[array_key_last($this->feedsInRedis)], $collection->timeTo()->getPreciseTimestamp(3), 'time to should match with first item');
        $this->assertEquals($this->feedsInRedis[array_key_last($this->feedsInRedis)], $collection->last(), 'last item\'s time should match');

    }

    public function testConcat()
    {
        $collection1 = new TimeSeriesCollection($this->feedsInDb);
        $collection2 = new TimeSeriesCollection($this->feedsInRedis);
        $collection = $collection1->concat($collection2);

        $this->assertEquals($this->feedsInDb[0]->uuid, $collection->uuids()->first(), 'first item should have a matching uuid');
        $this->assertEquals($this->feedsInDb[0]->created_at->timestamp, $collection->timeFrom()->timestamp, 'time from should match with first item');
        $this->assertEquals($this->feedsInDb[0]->created_at->getPreciseTimestamp(3), $collection->first(), 'first item\'s time should match');

        $this->assertEquals(array_key_last($this->feedsInRedis), $collection->uuids()->last(), 'last item should have a matching uuid');
        $this->assertEquals($this->feedsInRedis[array_key_last($this->feedsInRedis)], $collection->timeTo()->getPreciseTimestamp(3), 'time to should match with first item');
        $this->assertEquals($this->feedsInRedis[array_key_last($this->feedsInRedis)], $collection->last(), 'last item\'s time should match');

    }
}
