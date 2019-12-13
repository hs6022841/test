<?php

namespace App\Lib\Feed;

use App\Feed;

interface FeedContract {

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
     * Fetch the feed of a given actor
     *
     * @param $actorUserId
     * @param $offset
     * @param $limit
     * @return array|null
     */
    public function fetchFeed($actorUserId, $offset=0, $limit=50) : ?array;

    /**
     * Fetch the profile feed of a given actor
     *
     * @param $actorUserId
     * @param $offset
     * @param $limit
     * @return array|null
     */
    public function fetchProfileFeed($actorUserId, $offset=0, $limit=50) : ?array;

    /**
     * Post a new feed
     *
     * @param Feed $feed
     */
    public function postFeed(Feed $feed) : void;

    /**
     * Preload profile feed in cache
     * @param $userId
     * @param $count
     */
    public function preloadProfile($userId, $count) : void;

    /**
     * Preload feed in cache
     * @param $userId
     * @param $count
     */
    public function preloadFeed($userId, $count) : void;

    /**
     * Push feed to each user's feed
     *
     * @param array $params
     */
    public function fanoutFeed($params = []) : void;


    /**
     * Persist feed in cache into database
     */
    public function persist() : void;
}
