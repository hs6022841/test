<?php

namespace Tests\Unit;

use App\Events\FeedPosted;
use App\Feed;
use App\Lib\Feed\PushStrategy;
use App\Lib\FeedSubscriber\SubscribeToAll;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use Webpatser\Uuid\Uuid;

class PushStrategyTest extends TestCase
{
    use RefreshDatabase;

    protected $instance;
    protected $user1;
    protected $user2;
    protected $user3;

    function setUp(): void
    {
        parent::setUp();

        putenv("CACHE_TTL=600");
        putenv("BUFFER_PERSIST_TIMEOUT=60");

        $this->instance = new PushStrategy(new SubscribeToAll());

        $this->user1 = factory(User::class)->create([
            'id' => 1
        ]);

        $this->user2 = factory(User::class)->create([
            'id' => 2
        ]);

        $this->user3 = factory(User::class)->create([
            'id' => 3
        ]);
    }

    function tearDown(): void
    {
        // get rid of everything
        $prefix = Config::get('database.redis.options.prefix');
        $keys = Redis::keys('*');
        foreach($keys as $key) {
            Redis::del(str_replace($prefix, '', $key));
        }
        putenv("CACHE_TTL=600");
        putenv("BUFFER_PERSIST_TIMEOUT=60");
        parent::tearDown();
    }

    function testExa()
    {
        $this->assertTrue(true);
    }

    function testPostFeed()
    {

        Event::fake();

        $uuid = (string) Uuid::generate(1);
        $feed = (new Feed())->fill([
            'user_id' => 1,
            'uuid' => $uuid,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $this->instance->postFeed($feed);


        $this->assertEquals(1, Redis::exists("feed:$uuid"), 'feed has to be saved in cache');
        $this->assertEquals(1, Redis::exists("user:1:profile"), 'user profile should be created now');
        $this->assertEquals(1, Redis::exists("buffer:insert"), 'insert buffer should be created now');

        Event::assertDispatched(FeedPosted::class);
    }

    function testPersist()
    {
        // feed will be saved
        $uuid = (string) Uuid::generate(1);
        $feed1 = (new Feed())->fill([
            'user_id' => 1,
            'uuid' => $uuid,
            'comment' => '123',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // feed should be saved, but got deleted
        $uuid = (string) Uuid::generate(1);
        $feed2 = (new Feed())->fill([
            'user_id' => 1,
            'uuid' => $uuid,
            'comment' => '123',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // feed will be deleted
        $uuid = (string) Uuid::generate(1);
        $feed3 = (new Feed())->fill([
            'user_id' => 1,
            'uuid' => $uuid,
            'comment' => '123',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // feed will be deleted
        $uuid = (string) Uuid::generate(1);
        $feed4 = new Feed();
        $feed4->user_id = 1;
        $feed4->uuid = $uuid;
        $feed4->comment = '123';
        $feed4->created_at = Carbon::now();
        $feed4->updated_at = Carbon::now();
        $feed4->save();

        $this->instance->postFeed($feed1);
        $this->instance->postFeed($feed2);
        $this->instance->postFeed($feed3);
        $this->instance->deleteFeed($feed2);
        $this->instance->deleteFeed($feed4);

        $this->instance->persist();

        $this->assertDatabaseMissing('feeds', $feed1->toArray());
        $this->assertDatabaseMissing('feeds', $feed2->toArray());
        $this->assertDatabaseMissing('feeds', $feed3->toArray());
        $this->assertDatabaseHas('feeds', $feed4->toArray());


        $future = Carbon::now()->addMinutes(80);
        Carbon::setTestNow($future);

        $this->instance->persist();

        $this->assertDatabaseHas('feeds', $feed1->toArray());
        $this->assertDatabaseMissing('feeds', $feed2->toArray());
        $this->assertDatabaseHas('feeds', $feed3->toArray());
        $this->assertDatabaseMissing('feeds', $feed4->toArray());
    }

    function testFanoutFeed()
    {
        // user 1 himself as the owner should receive the feed
        Redis::zAdd('users', 1, 1);
        // user2 does not have a feed cache yet, he should not receive feed
        Redis::zAdd('users', 2, 2);
        // user3 have a feed cache, he should receive feed
        Redis::zAdd('users', 3, 3);

        $uuid = (string) Uuid::generate(1);
        Redis::zAdd('user:3:feed', 1000, $uuid);

        $uuid = (string) Uuid::generate(1);
        $feed = (new Feed())->fill([
            'user_id' => 1,
            'uuid' => $uuid,
            'comment' => '123',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $this->instance->fanoutFeed($feed);


        $ret = Redis::zRange('user:1:feed', 0, -1);
        $this->assertEquals(1, count($ret), 'owner should receive 1 feed');
        $ret = Redis::zRange('user:2:feed', 0, -1);
        $this->assertEquals(0, count($ret), 'user 2 should receive 0 feed');
        $ret = Redis::zRange('user:3:feed', 0, -1);
        $this->assertEquals(2, count($ret), 'user 3 should receive 2 feed');

    }
}
