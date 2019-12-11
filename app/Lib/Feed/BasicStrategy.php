<?php

namespace App\Lib\Feed;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class BasicStrategy implements FeedContract {
    /**
     * Follow an user
     *
     * In the basic strategy case, all users will be following to each other
     *
     * @param $actorUserId
     * @param $targetUserId
     */
    public function follow($actorUserId, $targetUserId): void
    {
        Redis::sadd('users', $actorUserId);
        $user = Redis::smembers('users');
        Log::error($user);
    }

    /**
     * Un-follow an user
     *
     * @param $actorUserId
     * @param $targetUserId
     */
    public function unfollow($actorUserId, $targetUserId): void
    {
        // not needed
    }
}
