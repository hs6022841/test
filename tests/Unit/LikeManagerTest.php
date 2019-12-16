<?php

namespace Tests\Unit;

use App\Feed;
use App\Lib\LikeManager;
use App\Lib\TimeSeriesPaginator;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class LikeManagerTest extends TestCase
{
    use RefreshDatabase;

    protected $publisher;
    protected $user;
    protected $feed1;
    protected $feed2;
    protected $feed3;
    protected $feed4;
    protected $feed5;
    protected $feeds;
    protected $instance;

    function setUp(): void
    {
        parent::setUp();
        $this->instance = new LikeManager();
        $this->publisher = factory(User::class)->create([
            'id' => 1
        ]);
        $this->user = factory(User::class)->create([
            'id' => 2
        ]);
        $this->feed1 = factory(Feed::class)->create([
            'user_id' => 1,
        ]);
        $this->feed2 = factory(Feed::class)->create([
            'user_id' => 1,
        ]);
        $this->feed3 = factory(Feed::class)->create([
            'user_id' => 1,
        ]);
        $this->feed4 = factory(Feed::class)->create([
            'user_id' => 1,
        ]);
        $this->feed5 = factory(Feed::class)->create([
            'user_id' => 1,
        ]);
        $this->feeds = new TimeSeriesPaginator([
            $this->feed1,
            $this->feed2,
            $this->feed3,
            $this->feed4,
            $this->feed5,
        ], 10);
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

    function testGet()
    {
        $likes = $this->instance->get($this->user, $this->feeds);
        $this->assertLikes($likes, -1, -1, -1, -1, -1);

        $this->instance->add($this->user, $this->feed1->uuid);
        $this->instance->add($this->user, $this->feed3->uuid);
        $likes = $this->instance->get($this->user, $this->feeds);
        // after calling add, like should be changed right away
        $this->assertLikes($likes, 1, -1, 1, -1, -1);

        // set one hour later to persist the buffer
        $newNow = Carbon::now()->addHour();
        Carbon::setTestNow($newNow);
        $this->instance->persist();

        // read from cache, result should be the same
        $likes = $this->instance->get($this->user, $this->feeds);
        $this->assertLikes($likes, 1, -1, 1, -1, -1);

        // remove feed 1 likes from the cache, result should be the same
        Redis::del('user:2:like:'. $this->feed1->uuid);
        $likes = $this->instance->get($this->user, $this->feeds);
        $this->assertLikes($likes, 1, -1, 1, -1, -1);
    }

    private function assertLikes($actual, $expect1, $expect2, $expect3, $expect4, $expect5)
    {
        $this->assertEquals($expect1, $actual[$this->feed1->uuid], "feed 1's like state is wrong");
        $this->assertEquals($expect2, $actual[$this->feed2->uuid], "feed 2's like state is wrong");
        $this->assertEquals($expect3, $actual[$this->feed3->uuid], "feed 3's like state is wrong");
        $this->assertEquals($expect4, $actual[$this->feed4->uuid], "feed 4's like state is wrong");
        $this->assertEquals($expect5, $actual[$this->feed5->uuid], "feed 5's like state is wrong");
    }

}
