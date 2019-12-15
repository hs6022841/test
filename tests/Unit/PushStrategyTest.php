<?php

namespace Tests\Unit;

use App\Events\FeedCachePreloaded;
use App\Events\FeedPosted;
use App\Events\ProfileCachePreloaded;
use App\Feed;
use App\Lib\FeedStrategy\PushStrategy;
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

    function testPostFeed()
    {

        Event::fake();

        $feed = factory(Feed::class)->make();
        $this->instance->postFeed($feed);

        $this->assertEquals(1, Redis::exists("feed:$feed->uuid"), 'feed has to be saved in cache');
        $this->assertEquals(1, Redis::exists("user:1:profile"), 'user profile should be created now');
        $this->assertEquals(1, Redis::exists("buffer:insert"), 'insert buffer should be created now');

        Event::assertDispatched(FeedPosted::class);
    }

    function testPersist()
    {
        // feed will be saved
        $feed1 = factory(Feed::class)->make();

        // feed should be saved, but got deleted
        $feed2 = factory(Feed::class)->make();

        // feed will be deleted
        $feed3 = factory(Feed::class)->make();

        // feed will be deleted
        // Hard code to prevent the time precision issue
        $feed4 = factory(Feed::class)->create();


        $this->instance->postFeed($feed1);
        $this->instance->postFeed($feed2);
        $this->instance->postFeed($feed3);
        $this->instance->deleteFeed($feed2);
        $this->instance->deleteFeed($feed4);

        $this->instance->persist();

        $this->assertDatabaseMissing('feeds', [
            'uuid' => $feed1->uuid,
        ]);
        $this->assertDatabaseMissing('feeds', [
            'uuid' => $feed2->uuid,
        ]);
        $this->assertDatabaseMissing('feeds', [
            'uuid' => $feed3->uuid,
        ]);
        $this->assertDatabaseHas('feeds', [
            'uuid' => $feed4->uuid,
        ]);


        $future = Carbon::now()->addMinutes(80);
        Carbon::setTestNow($future);

        $this->instance->persist();

        $this->assertDatabaseHas('feeds', [
            'uuid' => $feed1->uuid,
        ]);
        $this->assertDatabaseMissing('feeds', [
            'uuid' => $feed2->uuid,
        ]);
        $this->assertDatabaseHas('feeds', [
            'uuid' => $feed3->uuid,
        ]);
        $this->assertDatabaseMissing('feeds', [
            'uuid' => $feed4->uuid,
        ]);
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

        $feed = factory(Feed::class)->make();

        $this->instance->fanoutFeed($feed);


        $ret = Redis::zRange('user:1:feed', 0, -1);
        $this->assertEquals(1, count($ret), 'owner should receive 1 feed');
        $ret = Redis::zRange('user:2:feed', 0, -1);
        $this->assertEquals(0, count($ret), 'user 2 should receive 0 feed');
        $ret = Redis::zRange('user:3:feed', 0, -1);
        $this->assertEquals(2, count($ret), 'user 3 should receive 2 feed');

    }


    function testFetchUserFeed()
    {
        Event::fake();

        // Register user 2
        $feedSubscriberService = new SubscribeToAll();
        $feedSubscriberService->register(2);

        $createdAt = Carbon::now()->subDay();

        // user 2 post 1 feed into buffer in order to receive feed
        $feedsInCache = [];
        $feed = factory(Feed::class)->make([
            'user_id' => 2,
            'created_at' => $createdAt->subSeconds(0)
        ]);
        $this->instance->postFeed($feed);
        $this->instance->fanoutFeed($feed);
        $feedsInCache[] = $feed;

        // user 1 post 4 feed into buffer
        for($i = 1; $i < 5; $i++) {
            $feed = factory(Feed::class)->make([
                'user_id' => 1,
                'created_at' => $createdAt->subSeconds($i)
            ]);
            $this->instance->postFeed($feed);
            $this->instance->fanoutFeed($feed);
            $feedsInCache[] = $feed;
        }


        // user 1 had 5 feed inside database which is not preload into cache yet
        $feedsInDb = [];
        for($i = 0; $i < 5; $i++) {
            $feedsInDb[] = factory(Feed::class)->create([
                'user_id' => 1,
                'created_at' => $createdAt->subSeconds(10 + $i)
            ]);
        }

        // first page
        $ret = $this->instance->getFeed($this->user2, Carbon::now(), 2);
        $this->assertEquals($feedsInCache[0]->uuid, $ret->items()[0]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInCache[1]->uuid, $ret->items()[1]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInCache[1]->created_at->timestamp, $ret->timeTo()->timestamp, 'next time have to match');

        // second page
        $ret = $this->instance->getFeed($this->user2, $ret->timeTo(), 2);
        $this->assertEquals($feedsInCache[2]->uuid, $ret->items()[0]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInCache[3]->uuid, $ret->items()[1]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInCache[3]->created_at->timestamp, $ret->timeTo()->timestamp, 'next time have to match');

        // third page
        $ret = $this->instance->getFeed($this->user2, $ret->timeTo(), 2);
        $this->assertEquals($feedsInCache[4]->uuid, $ret->items()[0]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInDb[0]->uuid, $ret->items()[1]->uuid, 'items in paginator have to match with whats being created in db');
        $this->assertEquals($feedsInDb[0]->created_at->timestamp, $ret->timeTo()->timestamp, 'next time have to match');
        Event::assertDispatched(FeedCachePreloaded::class);

        // forth page
        $ret = $this->instance->getFeed($this->user2, $ret->timeTo(), 2);
        $this->assertEquals($feedsInDb[1]->uuid, $ret->items()[0]->uuid, 'items in paginator have to match with whats being created in db');
        $this->assertEquals($feedsInDb[2]->uuid, $ret->items()[1]->uuid, 'items in paginator have to match with whats being created in db');
        $this->assertEquals($feedsInDb[2]->created_at->timestamp, $ret->timeTo()->timestamp, 'next time have to match');
        Event::assertDispatched(FeedCachePreloaded::class);

        // fifth page
        $ret = $this->instance->getFeed($this->user2, $ret->timeTo(), 2);
        $this->assertEquals($feedsInDb[3]->uuid, $ret->items()[0]->uuid, 'items in paginator have to match with whats being created in db');
        $this->assertEquals($feedsInDb[4]->uuid, $ret->items()[1]->uuid, 'items in paginator have to match with whats being created in db');
        $this->assertEquals($feedsInDb[4]->created_at->timestamp, $ret->timeTo()->timestamp, 'next time have to match');
        Event::assertDispatched(FeedCachePreloaded::class);
    }

    function testFetchUserFeedWithDelete()
    {
        Event::fake();
        $createdAt = Carbon::now()->subDay();
        // user 1 post 5 feed into buffer
        $feedsInCache = [];
        for($i = 0; $i < 5; $i++) {
            $feed = factory(Feed::class)->make([
                'user_id' => 1,
                'created_at' => $createdAt->subSeconds($i)
            ]);
            $this->instance->postFeed($feed);
            $feedsInCache[] = $feed;
        }


        // user 1 had 5 feed inside database which is not preload into cache yet
        $feedsInDb = [];
        for($i = 0; $i < 5; $i++) {
            $feedsInDb[] = factory(Feed::class)->create([
                'user_id' => 1,
                'created_at' => $createdAt->subSeconds(10 + $i)
            ]);
        }

        // delete one feed in buffer
        $this->instance->deleteFeed($feedsInCache[1]);

        // delete one feed in db
        $this->instance->deleteFeed($feedsInDb[3]);

        $this->instance->persist();

        // first page
        $ret = $this->instance->getFeed($this->user1, Carbon::now(), 6);
        $this->assertEquals($feedsInCache[0]->uuid, $ret->items()[0]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInDb[1]->uuid, $ret->items()[5]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInDb[1]->created_at->timestamp, $ret->timeTo()->timestamp, 'next time have to match');
        $this->assertEquals(6, count($ret->items()), 'should fetch 6 items');

        // second page
        $ret = $this->instance->getFeed($this->user1, $ret->timeTo(), 6);
        $this->assertEquals($feedsInDb[2]->uuid, $ret->items()[0]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInDb[4]->uuid, $ret->items()[1]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInDb[4]->created_at->timestamp, $ret->timeTo()->timestamp, 'next time have to match');
        $this->assertEquals(2, count($ret->items()), 'should fetch 2 items');
        Event::assertDispatched(FeedCachePreloaded::class);
    }

    function testFetchProfileFeed()
    {
        Event::fake();
        $createdAt = Carbon::now()->subDay();
        // user 1 post 5 feed into buffer
        $feedsInCache = [];
        for($i = 0; $i < 5; $i++) {
            $feed = factory(Feed::class)->make([
                'user_id' => 1,
                'created_at' => $createdAt->subSeconds($i)
            ]);
            $this->instance->postFeed($feed);
            $feedsInCache[] = $feed;
        }

        // user 1 had 5 feed inside database which is not preload into cache yet
        $feedsInDb = [];
        for($i = 0; $i < 5; $i++) {
            $feedsInDb[] = factory(Feed::class)->create([
                'user_id' => 1,
                'created_at' => $createdAt->subSeconds(10 + $i)
            ]);
        }

        // first page
        $ret = $this->instance->getProfile($this->user1, Carbon::now(), 2);
        $this->assertEquals($feedsInCache[0]->uuid, $ret->items()[0]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInCache[1]->uuid, $ret->items()[1]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInCache[1]->created_at->timestamp, $ret->timeTo()->timestamp, 'next time have to match');

        // second page
        $ret = $this->instance->getProfile($this->user1, $ret->timeTo(), 2);
        $this->assertEquals($feedsInCache[2]->uuid, $ret->items()[0]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInCache[3]->uuid, $ret->items()[1]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInCache[3]->created_at->timestamp, $ret->timeTo()->timestamp, 'next time have to match');

        // third page
        $ret = $this->instance->getProfile($this->user1, $ret->timeTo(), 2);
        $this->assertEquals($feedsInCache[4]->uuid, $ret->items()[0]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInDb[0]->uuid, $ret->items()[1]->uuid, 'items in paginator have to match with whats being created in db');
        $this->assertEquals($feedsInDb[0]->created_at->timestamp, $ret->timeTo()->timestamp, 'next time have to match');
        Event::assertDispatched(ProfileCachePreloaded::class);

        // forth page
        $ret = $this->instance->getProfile($this->user1, $ret->timeTo(), 2);
        $this->assertEquals($feedsInDb[1]->uuid, $ret->items()[0]->uuid, 'items in paginator have to match with whats being created in db');
        $this->assertEquals($feedsInDb[2]->uuid, $ret->items()[1]->uuid, 'items in paginator have to match with whats being created in db');
        $this->assertEquals($feedsInDb[2]->created_at->timestamp, $ret->timeTo()->timestamp, 'next time have to match');
        Event::assertDispatched(ProfileCachePreloaded::class);

        // fifth page
        $ret = $this->instance->getProfile($this->user1, $ret->timeTo(), 2);
        $this->assertEquals($feedsInDb[3]->uuid, $ret->items()[0]->uuid, 'items in paginator have to match with whats being created in db');
        $this->assertEquals($feedsInDb[4]->uuid, $ret->items()[1]->uuid, 'items in paginator have to match with whats being created in db');
        $this->assertEquals($feedsInDb[4]->created_at->timestamp, $ret->timeTo()->timestamp, 'next time have to match');
        Event::assertDispatched(ProfileCachePreloaded::class);

    }

    function testFetchProfileFeedWithDelete()
    {
        Event::fake();
        $createdAt = Carbon::now()->subDay();
        // user 1 post 5 feed into buffer
        $feedsInCache = [];
        for($i = 0; $i < 5; $i++) {
            $feed = factory(Feed::class)->make([
                'user_id' => 1,
                'created_at' => $createdAt->subSeconds($i)
            ]);
            $this->instance->postFeed($feed);
            $feedsInCache[] = $feed;
        }


        // user 1 had 5 feed inside database which is not preload into cache yet
        $feedsInDb = [];
        for($i = 0; $i < 5; $i++) {
            $feedsInDb[] = factory(Feed::class)->create([
                'user_id' => 1,
                'created_at' => $createdAt->subSeconds(10 + $i)
            ]);
        }

        // delete one feed in buffer
        $this->instance->deleteFeed($feedsInCache[1]);

        // delete one feed in db
        $this->instance->deleteFeed($feedsInDb[3]);

        $this->instance->persist();

        // first page
        $ret = $this->instance->getProfile($this->user1, Carbon::now(), 6);
        $this->assertEquals($feedsInCache[0]->uuid, $ret->items()[0]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInDb[1]->uuid, $ret->items()[5]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInDb[1]->created_at->timestamp, $ret->timeTo()->timestamp, 'next time have to match');
        $this->assertEquals(6, count($ret->items()), 'should fetch 6 items');

        // second page
        $ret = $this->instance->getProfile($this->user1, $ret->timeTo(), 6);
        $this->assertEquals($feedsInDb[2]->uuid, $ret->items()[0]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInDb[4]->uuid, $ret->items()[1]->uuid, 'items in paginator have to match with whats being created in cache');
        $this->assertEquals($feedsInDb[4]->created_at->timestamp, $ret->timeTo()->timestamp, 'next time have to match');
        $this->assertEquals(2, count($ret->items()), 'should fetch 2 items');
        Event::assertDispatched(ProfileCachePreloaded::class);
    }


    function testPreloadProfile()
    {
        $createdAt = Carbon::now()->subDay();
        // user 1 post 5 feed into buffer
        $feedsInCache = [];
        for($i = 0; $i < 5; $i++) {
            $feed = factory(Feed::class)->make([
                'user_id' => 1,
                'created_at' => $createdAt->subSeconds($i)
            ]);
            $this->instance->postFeed($feed);
            $feedsInCache[] = $feed;
        }


        // user 1 had 5 feed inside database which is not preload into cache yet
        $feedsInDb = [];
        for($i = 0; $i < 5; $i++) {
            $feedsInDb[] = factory(Feed::class)->create([
                'user_id' => 1,
                'created_at' => $createdAt->subSeconds(10 + $i)
            ]);
        }

        $now = Carbon::now();
        $this->instance->preloadProfile($this->user1, $now);

        // in cache feed should still be there
        foreach($feedsInCache as $key=>$feed) {
            $score = Redis::zScore('user:1:profile', $feed->uuid);
            $this->assertEquals($feed->created_at->timestamp, (int) ($score/1000), 'Feed should exist in cache');
        }
        foreach($feedsInDb as $feed) {
            $score = Redis::zScore('user:1:profile', $feed->uuid);
            $this->assertEquals($feed->created_at->timestamp, (int) ($score/1000), 'Feed should exist in cache');
        }
    }

    function testPreloadFeed()
    {
        // Register user 2
        $feedSubscriberService = new SubscribeToAll();
        $feedSubscriberService->register(2);

        $createdAt = Carbon::now()->subDay();

        // user 2 post 1 feed into buffer in order to receive feed
        $feedsInCache = [];
        $feed = factory(Feed::class)->make([
            'user_id' => 2,
            'created_at' => $createdAt->subSeconds(0)
        ]);
        $this->instance->postFeed($feed);
        $this->instance->fanoutFeed($feed);
        $feedsInCache[] = $feed;

        // user 1 post 4 feed into buffer
        for($i = 1; $i < 5; $i++) {
            $feed = factory(Feed::class)->make([
                'user_id' => 1,
                'created_at' => $createdAt->subSeconds($i)
            ]);
            $this->instance->postFeed($feed);
            $this->instance->fanoutFeed($feed);
            $feedsInCache[] = $feed;
        }


        // user 1 had 5 feed inside database which is not preload into cache yet
        $feedsInDb = [];
        for($i = 0; $i < 5; $i++) {
            $feedsInDb[] = factory(Feed::class)->create([
                'user_id' => 1,
                'created_at' => $createdAt->subSeconds(10 + $i)
            ]);
        }

        $now = Carbon::now();
        $this->instance->preloadFeed($this->user2, $now);

        // in cache feed should still be there
        foreach($feedsInCache as $feed) {
            $score = Redis::zScore('user:2:feed', $feed->uuid);
            $this->assertEquals($feed->created_at->timestamp, (int) ($score/1000), 'Feed should exist in cache');
        }
        foreach($feedsInDb as $feed) {
            $score = Redis::zScore('user:2:feed', $feed->uuid);
            $this->assertEquals($feed->created_at->timestamp, (int) ($score/1000), 'Feed should exist in cache');
        }

    }

}
