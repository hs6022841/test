<?php

namespace App\Lib\Feed;

use App\Feed;
use Illuminate\Pagination\Paginator;

interface FeedContract
{
    /**
     * Fetch the feed of a given actor
     *
     * @param $actorUserId
     * @param $offset
     * @param $limit
     * @return Paginator
     */
    public function fetchFeed($actorUserId, $offset=0, $limit=50) : Paginator;

    /**
     * Fetch the profile feed of a given actor
     *
     * @param $actorUserId
     * @param $offset
     * @param $limit
     * @return Paginator
     */
    public function fetchProfileFeed($actorUserId, $offset=0, $limit=50) : Paginator;

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
     * @param Feed $feed
     */
    public function fanoutFeed(Feed $feed) : void;


    /**
     * Persist feed in cache into database
     */
    public function persist() : void;
}
