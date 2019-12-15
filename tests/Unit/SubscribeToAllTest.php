<?php

namespace Tests\Unit;

use App\Lib\FeedSubscriber\SubscribeToAll;
use App\User;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubscribeToAllTest extends TestCase
{
    use RefreshDatabase;

    protected $user1;
    protected $user2;
    protected $instance;

    protected $key = 'users';

    function setUp(): void
    {
        $this->instance = new SubscribeToAll();

        parent::setUp();
        $this->user1 = factory(User::class)->create([
            'id' => 1
        ]);
        $this->user2 = factory(User::class)->create([
            'id' => 2
        ]);
    }

    function tearDown(): void
    {
        Redis::del($this->key);
        parent::tearDown();
    }

    public function testLoadFollowers()
    {
        $this->instance->loadFollowers(1);
        $users = Redis::zRange($this->key, 0, -1);

        $this->assertEquals(1, $users[0], 'User id 1 has to exist in cache');
        $this->assertEquals(2, $users[1], 'User id 2 has to exist in cache');
    }

    public function testFanoutToFollowers()
    {
        $i = 1;
        $count = 45;
        while($i <= $count) {
            Redis::zAdd($this->key, $i, $i);
            $i++;
        }

        $pageSize = 10;
        $currentPage = 0;

        $this->instance->fanoutToFollowers(1, function($users) use ($count, $pageSize, &$currentPage) {
            $lastUserId = end($users);
            $expectLastId = ($currentPage + 1) * $pageSize;
            if($expectLastId > $count) {
                $expectLastId = $currentPage * $pageSize + $count % $pageSize;
            }

            $this->assertEquals($lastUserId, $expectLastId, 'Fanout user pagination doesnt match' . "pagesize:$pageSize, current: $currentPage, expected: $expectLastId, actural: $lastUserId");
            $currentPage++;
        }, $pageSize);

    }
}
