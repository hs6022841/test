<?php

namespace App\Lib\FeedStrategy;

use App\Feed;
use App\Lib\TimeSeriesPaginator;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;

interface FeedContract
{
    /**
     * Fetch the feed of a given actor
     *
     * @param $actor
     * @param $time
     * @param $limit
     * @return TimeSeriesPaginator
     */
    public function getFeed(Authenticatable $actor, Carbon $time = null, $limit = 50) : TimeSeriesPaginator;

    /**
     * Fetch the profile feed of a given actor
     *
     * @param $actor
     * @param $time
     * @param $limit
     * @return TimeSeriesPaginator
     */
    public function getProfile(Authenticatable $actor, Carbon $time = null, $limit = 50) : TimeSeriesPaginator;

    /**
     * Post a new feed
     *
     * @param Feed $feed
     */
    public function postFeed(Feed $feed) : void;

    /**
     * Get detail of a feed
     *
     * @param $uuid
     * @return Feed
     */
    public function lookupFeed($uuid) : Feed;

    /**
     * Delete a feed
     *
     * @param Feed $feed
     */
    public function deleteFeed(Feed $feed) : void;

    /**
     * Preload profile feed in cache
     * @param $user
     * @param $time
     */
    public function preloadProfile(Authenticatable $user, Carbon $time) : void;

    /**
     * Preload feed in cache
     * @param $user
     * @param $time
     */
    public function preloadFeed(Authenticatable $user, Carbon $time) : void;

    /**
     * Push feed to each user's feed
     *
     * @param Feed $feed
     * @param bool $isInsert
     */
    public function fanoutFeed(Feed $feed, $isInsert = true) : void;


    /**
     * Persist feed in cache into database
     */
    public function persist() : void;
}
