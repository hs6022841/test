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

    public function testLoginLogout()
    {
        $this->instance->register(1);
        $users = Redis::zRange($this->key, 0, -1);
        $this->assertEquals(1, $users[0], 'User id 1 should being registered');

        $this->instance->register(2);
        $users = Redis::zRange($this->key, 0, -1);
        $this->assertEquals(2, $users[1], 'User id 2 should being registered');

        $this->instance->deregister(2);
        $users = Redis::zRange($this->key, 0, -1);
        $this->assertEquals(1, count($users), 'should have only 1 user left in the list');
        $this->assertEquals(1, $users[0], 'user 1 is the only one left');
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
