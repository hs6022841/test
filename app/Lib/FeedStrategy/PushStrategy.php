<?php

namespace App\Lib\FeedStrategy;

use App\Events\FeedCachePreloaded;
use App\Events\FeedPosted;
use App\Events\ProfileCachePreloaded;
use App\Feed;
use App\Lib\FeedManager\ProfileFeedManager;
use App\Lib\FeedManager\UserFeedManager;
use App\Lib\TimeSeriesCollection;
use App\Lib\TimeSeriesPaginator;
use App\User;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PushStrategy extends StrategyBase implements FeedContract {


    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function getFeed(Authenticatable $actor, Carbon $time = null, $limit = 50) : TimeSeriesPaginator
    {
        return (new UserFeedManager($actor))->get($time, $limit);
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function getProfile(Authenticatable $actor, Carbon $time = null, $limit = 50) : TimeSeriesPaginator
    {
        return (new ProfileFeedManager($actor))->get($time, $limit);
    }

    /**
     * Post a new feed
     *
     * @param Feed $feed
     */
    public function postFeed(Feed $feed) : void {
        $time = $feed->created_at;
        Redis::multi()
            ->hMSet($this->getFeedKey($feed->uuid), $feed->toArray())
            ->expire($this->getFeedKey($feed->uuid), $this->cacheTTL)
            ->zAdd($this->getProfileKey($feed->user_id), $time->getPreciseTimestamp(3), $feed->uuid)
            ->expire($this->getProfileKey($feed->user_id), $this->cacheTTL);

        $this->buffer->add($feed->uuid, $time);
        Redis::exec();

        // dispatch the event for feed fanout process
        // Note that passing an uncommitted feed across event is going to hang the process
        event(new FeedPosted($feed->toArray()));
    }

    /**
     * @inheritDoc
     */
    public function lookupFeed($uuid): Feed
    {
        return feed::findFeedByUuid($uuid);
    }

    /**
     * @inheritDoc
     */
    public function deleteFeed(Feed $feed): void
    {
        $this->buffer->delete($feed->uuid, $feed->created_at);
    }

    /**
     * @inheritDoc
     */
    public function preloadProfile(Authenticatable $user, Carbon $time): void
    {
        (new ProfileFeedManager($user))->preload($time);
    }

    /**
     * @inheritDoc
     */
    public function preloadFeed(Authenticatable $user, Carbon $time): void
    {
        (new UserFeedManager($user))->preload($time);
    }

    /**
     * @inheritDoc
     */
    public function persist() : void {
        $this->buffer->persist(function($uuids) {
            $this->persistInsertion($uuids);
        }, function($uuids) {
            $this->persistDeletion($uuids);
        });
    }

    /**
     * @inheritDoc
     */
    public function fanoutFeed(Feed $feed) : void {
        $this->feedSubscriberService->fanoutToFollowers($feed->user_id, function($userIds) use ($feed) {
            $feed->attachToUser($userIds);
        });
    }
}
