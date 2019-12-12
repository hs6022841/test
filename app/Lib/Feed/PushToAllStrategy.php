<?php

namespace App\Lib\Feed;

use App\Events\FeedPosted;
use App\Feed;
use App\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PushToAllStrategy implements FeedContract {

    /**
     * Initial following list for newly registered users
     *
     * In the basic strategy case, all users will be following to each other
     *
     * @param $actorUserId
     * @param array $targetUserIds
     */
    public function setup($actorUserId, $targetUserIds = []) : void
    {
        // using a
        Redis::zAdd('users', $actorUserId, $actorUserId);
    }

    /**
     * Follow an user
     *
     *
     * @param $actorUserId
     * @param $targetUserId
     */
    public function follow($actorUserId, $targetUserId): void
    {
        // not needed
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

    /**
     * Fetch the feed of a given actor
     *
     * @param $actorUserId
     * @param $offset
     * @param $limit
     * @return array|null
     */
    public function fetchFeed($actorUserId, $offset=0, $limit=50) : ?array
    {
        $exist = Redis::exists('users');
        // TODO: re-construct cache if key is missing, this should Ideally be handled elsewhere
        if(!$exist) {
            $users = User::pluck('id');
            foreach($users as $user) {
                Redis::zAdd('users', $user, $user);
            }
        }

        $feedUuids = Redis::zRange("user-feed:$actorUserId", $offset, $limit);

        $feeds = [];
        foreach($feedUuids as $feedUuid) {
            $found = Redis::hGetAll("feed:$feedUuid");

            if(!empty($found)) {
                $found = (new Feed())->fill($found);
            } else {
                $found = Feed::where('uuid', $feedUuid)->first();
            }

            if(empty($found)) {
                Log::error("Feed $feedUuid does not exist in both cache and db, something was wrong");
                continue;
            }

            $feeds[] = $found;
        }

        return $feeds;
    }

    /**
     * Post a new feed
     *
     * @param Feed $feed
     */
    public function postFeed(Feed $feed) : void {
        Redis::hMSet("feed:$feed->uuid", $feed->toArray());
        Redis::zAdd('feed-buffer-list', $feed->created_at->timestamp, $feed->uuid);

        // dispatch the event for feed fanout process
        event(new FeedPosted($feed->uuid, $feed->created_at->timestamp));
    }
}
