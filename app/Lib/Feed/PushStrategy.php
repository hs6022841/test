<?php

namespace App\Lib\Feed;

use App\Events\FeedCacheWarmUp;
use App\Events\FeedPosted;
use App\Events\ProfileCacheWarmUp;
use App\Feed;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Redis;

class PushStrategy extends StrategyBase implements FeedContract {


    /**
     * @inheritDoc
     */
    public function fetchFeed($actorUserId, $offset=0, $limit=50) : Paginator
    {
        $uuids = Redis::zRangeByScore($this->getUserFeedKey($actorUserId), $offset, $limit);

        if(empty($uuids)) {
            $uuids = $this->buffer->get($offset, $limit);
            if(count($uuids) < $limit) {
                $uuidsFromDb = $this->loadFeedFromDb($offset, $limit - count($uuids));
                $uuids = array_merge($uuids, $uuidsFromDb);
            }
            event(new FeedCacheWarmUp($actorUserId, $this->getWarmUpSize($offset, $limit)));
        }

        // TODO: should keep on fetching recursively untill meet limit
        $uuids = $this->findFeedByUuid($uuids);
        return new Paginator($uuids, $limit, (int) $offset/$limit);
    }


    /**
     * @inheritDoc
     */
    public function fetchProfileFeed($actorUserId, $offset=0, $limit=50) : Paginator
    {
        $uuids = Redis::zRange($this->getProfileKey($actorUserId), $offset, $limit);

        if(empty($uuids)) {
            // if no cache exists,
            $offset = 0;
            $uuids = $this->loadProfileFromDb($actorUserId, $offset, $limit);
            event(new ProfileCacheWarmUp($actorUserId, $this->getWarmUpSize($offset, $limit)));
        }
        // TODO: should keep on fetching recursively untill meet limit
        $uuids = $this->findFeedByUuid($uuids);
        return new Paginator($uuids, $limit, (int) $offset/$limit);

    }

    /**
     * Post a new feed
     *
     * @param Feed $feed
     */
    public function postFeed(Feed $feed) : void {
        Redis::multi()
            ->hMSet($this->getFeedKey($feed->uuid), $feed->toArray())
            ->expire($this->getFeedKey($feed->uuid), $this->cacheTTL)
            ->zAdd($this->getProfileKey($feed->user_id), $feed->created_at->timestamp * -1, $feed->uuid)
            ->expire($this->getProfileKey($feed->user_id), $this->cacheTTL);

        $this->buffer->add($feed->uuid, $feed->created_at->timestamp);

        Redis::exec();

        // dispatch the event for feed fanout process
        // Note that passing an uncommitted feed across event is going to hang the process
        event(new FeedPosted($feed->toArray()));
    }

    /**
     * @inheritDoc
     */
    public function preloadProfile($userId, $count): void
    {
        // TODO: Query db, build up cache
    }

    /**
     * @inheritDoc
     */
    public function preloadFeed($userId, $count): void
    {
        // TODO: Query db, build up cache
    }

    /**
     * @inheritDoc
     */
    public function persist() : void {
        $this->buffer->persist(function($uuids) {
            $this->saveFeedToDb($uuids);
        });
    }

    /**
     * @inheritDoc
     */
    public function fanoutFeed(Feed $feed) : void {
        $this->feedSubscriberService->fanoutToFollowers($feed->user_id, function($userIds) use ($feed) {
            $targets = [];
            // no need to push to none exist feeds, as they are not active
            foreach($userIds as $userId) {
                $exists = Redis::exists($this->getUserFeedKey($userId));
                if(!$exists) continue;
                $targets[] = $userId;
            }

            Redis::pipeline(function ($pipe) use ($targets, $feed) {
                foreach($targets as $userId) {
                    // making the score negative so that the timeline is desc
                    $pipe->zAdd($this->getUserFeedKey($userId), $feed->created_at->timestamp * -1, $feed->uuid)
                        ->expire($this->getUserFeedKey($userId), $this->cacheTTL);
                }
            });
        });
    }
}
