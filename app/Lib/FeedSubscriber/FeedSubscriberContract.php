<?php


namespace App\Lib\FeedSubscriber;


interface FeedSubscriberContract
{
    /**
     * Initial following list for newly registered users
     *
     * @param $actorUserId
     * @param array $targetUserIds
     */
    public function setup($actorUserId, $targetUserIds = []) : void;

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

    /**
     * Load user's follower
     *
     * @param $actorUserId
     */
    public function loadFollowers($actorUserId) : void;

    /**
     * Load user's followee
     *
     * @param $actorUserId
     */
    public function loadFollowee($actorUserId) : void;

    /**
     * Find subscribers and execute the fanout
     *
     * @param $actorId
     * @param \Closure $fanout
     */
    public function fanoutToFollowers($actorId, \Closure $fanout) : void;
}
