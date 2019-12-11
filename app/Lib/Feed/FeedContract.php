<?php

namespace App\Lib\Feed;

interface FeedContract {
    /**
     * Follow an user
     *
     * @param $actorUserId
     * @param $targetUserId
     */
    public function follow($actorUserId, $targetUserId) : void;

    /**
     * Un-follow an user
     *
     * @param $actorUserId
     * @param $targetUserId
     */
    public function unfollow($actorUserId, $targetUserId) : void;
}
