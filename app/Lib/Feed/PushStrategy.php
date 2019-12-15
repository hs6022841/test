<?php

namespace App\Lib\Feed;

use App\Events\FeedCacheWarmUp;
use App\Events\FeedPosted;
use App\Events\ProfileCacheWarmUp;
use App\Feed;
use App\Lib\TimeSeriesPaginator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PushStrategy extends StrategyBase implements FeedContract {


    /**
     * @inheritDoc
     */
    public function fetchFeed($actorUserId, Carbon $time = null, $limit = 50) : TimeSeriesPaginator
    {
        $time = is_null($time) ? Carbon::now() : $time;

        $paginator = $this->loadFeed(
            $this->getUserFeedKey($actorUserId),
            $actorUserId,
            $time,
            $limit,
            FeedCacheWarmUp::class,
            function($time, $limit) {
                return $this->loadFeedFromDb($time, $limit);
            }
        );

        // TODO: should keep on fetching recursively untill meet limit/ maybe should put this outside somewhere
        $uuids = $this->findFeedByUuid($paginator->items());
        return $paginator;
    }


    /**
     * @inheritDoc
     */
    public function fetchProfileFeed($actorUserId, Carbon $time = null, $limit = 50) : TimeSeriesPaginator
    {
        $time = is_null($time) ? Carbon::now() : $time;

        $paginator = $this->loadFeed(
            $this->getProfileKey($actorUserId),
            $actorUserId,
            $time,
            $limit,
            ProfileCacheWarmUp::class,
            function($time, $limit) use ($actorUserId) {
                return $this->loadFeedFromDb($time, $limit, $actorUserId);
            }
        );

        return $paginator;

    }

    protected function loadFeed($key, $actorUserId, $time, $limit, $event, \Closure $dbQuery) {
        // fetched from cache
        $ret = get_timeseries($key, $time, $limit);
        if($ret->count() < $limit) {
            // fetch the reset from db
            $dbTime = $ret->count() == 0 ? $time : Carbon::createFromTimestampMs($ret->toTime());
            $dbLimit = $limit - $ret->count();
            $dbRet = $dbQuery($dbTime, $dbLimit);
            $ret = $ret->concatPaginator($dbRet);
            // if there are data returned, we start to warm up cache
            if(count($dbRet) != 0) {
                event(new $event($actorUserId, $time));
            }
        }
        return $ret;
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
    public function deleteFeed(Feed $feed): void
    {
        $this->buffer->delete($feed->uuid, $feed->created_at);
    }

    /**
     * @inheritDoc
     */
    public function preloadProfile($userId, Carbon $time): void
    {
        // FIXME: should have a mechanism to decide the preload size,
        // should probably based on how active the user is or how deep the provided time is
        // For now, load everything
        $time = Carbon::now();
        while(true) {
            $paginator = $this->loadFeedFromDb($time, 100, $userId);
            if($paginator->count() == 0) {
                break;
            }
            $time = Carbon::createFromTimestampMs($paginator->toTime());
            $feeds = $paginator->getMapping();
            Redis::pipeline(function ($pipe) use ($userId, $feeds) {
                foreach($feeds as $uuid=>$createdAt) {
                    // making the score negative so that the timeline is desc
                    $pipe->zAdd($this->getProfileKey($userId), $createdAt, $uuid)
                        ->expire($this->getProfileKey($userId), $this->cacheTTL);
                }
            });
        }

    }

    /**
     * @inheritDoc
     */
    public function preloadFeed($userId, Carbon $time): void
    {
        // FIXME: should have a mechanism to decide the preload size,
        // should probably based on how active the user is or how deep the provided time is
        // For now, load everything
        $time = Carbon::now();
        while(true) {
            $paginator = $this->loadFeedFromDb($time, 100);
            if($paginator->count() == 0) {
                break;
            }
            $time = Carbon::createFromTimestampMs($paginator->toTime());
            $feeds = $paginator->getMapping();
            Redis::pipeline(function ($pipe) use ($userId, $feeds) {
                foreach($feeds as $uuid=>$createdAt) {
                    // making the score negative so that the timeline is desc
                    $pipe->zAdd($this->getUserFeedKey($userId), $createdAt, $uuid)
                        ->expire($this->getUserFeedKey($userId), $this->cacheTTL);
                }
            });
        }
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
            // no need to push to none exist feeds, as they are not active
            foreach($userIds as $key=>$userId) {
                $exists = Redis::exists($this->getUserFeedKey($userId));
                if(!$exists && $userId != $feed->user_id) unset($userIds[$key]);
            }

            $this->addFeedsToTargets($userIds, $feed);
        });
    }
}
