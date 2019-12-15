<?php

namespace App\Lib\Feed;

use App\Feed;
use App\Lib\TimeSeriesPaginator;
use Carbon\Carbon;

interface FeedContract
{
    /**
     * Fetch the feed of a given actor
     *
     * @param $actorUserId
     * @param $time
     * @param $limit
     * @return TimeSeriesPaginator
     */
    public function fetchUserFeed($actorUserId, Carbon $time = null, $limit = 50) : TimeSeriesPaginator;

    /**
     * Fetch the profile feed of a given actor
     *
     * @param $actorUserId
     * @param $time
     * @param $limit
     * @return TimeSeriesPaginator
     */
    public function fetchProfileFeed($actorUserId, Carbon $time = null, $limit = 50) : TimeSeriesPaginator;

    /**
     * Post a new feed
     *
     * @param Feed $feed
     */
    public function postFeed(Feed $feed) : void;

    /**
     * Delete a feed
     *
     * @param Feed $feed
     */
    public function deleteFeed(Feed $feed) : void;

    /**
     * Preload profile feed in cache
     * @param $userId
     * @param $time
     */
    public function preloadProfile($userId, Carbon $time) : void;

    /**
     * Preload feed in cache
     * @param $userId
     * @param $time
     */
    public function preloadFeed($userId, Carbon $time) : void;

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
