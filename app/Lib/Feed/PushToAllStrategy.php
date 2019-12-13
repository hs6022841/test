<?php

namespace App\Lib\Feed;

use App\Events\FeedCacheWarmUp;
use App\Events\FeedPosted;
use App\Events\ProfileCacheWarmUp;
use App\Feed;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PushToAllStrategy implements FeedContract {

    protected $cacheTTL;
    protected $bufferTimeout;

    public function __construct()
    {
        $this->cacheTTL = env('FEED_CACHE_TTL', 60);
        $this->bufferTimeout = env('BUFFER_PERSIST_TIMEOUT', 10);
    }

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
        // using sorted set here so that we can slice the set during the fanout process
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
     * @throws \Exception
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

        $feedUuids = Redis::zRange("user:$actorUserId:feed", $offset, $limit);
        if(empty($feedUuids)) {
            $feedUuids = Feed::OrderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->pluck('uuid')
                ->toArray();
            event(new FeedCacheWarmUp($actorUserId, $this->getWarmUpDataCount($offset, $limit)));
        }

        return $this->findFeedByUuid($feedUuids);
    }


    /**
     * Fetch the profile feed of a given actor
     *
     * @param $actorUserId
     * @param $offset
     * @param $limit
     * @return array|null
     * @throws \Exception
     */
    public function fetchProfileFeed($actorUserId, $offset=0, $limit=50) : ?array
    {
        $feedUuids = Redis::zRange("user:$actorUserId:profile", $offset, $limit);

        if(empty($feedUuids)) {
            $feedUuids = Feed::where('user_id', $actorUserId)
                ->OrderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->pluck('uuid')
                ->toArray();
            event(new ProfileCacheWarmUp($actorUserId, $this->getWarmUpDataCount($offset, $limit)));
        }

        return $this->findFeedByUuid($feedUuids);
    }

    /**
     * Post a new feed
     *
     * @param Feed $feed
     */
    public function postFeed(Feed $feed) : void {
        Redis::multi()
            ->hMSet("feed:$feed->uuid", $feed->toArray())
            ->expire("feed:$feed->uuid", $this->cacheTTL)
            ->zAdd("user:$feed->user_id:profile", $feed->created_at->timestamp * -1, $feed->uuid)
            ->expire("user:$feed->user_id:profile", $this->cacheTTL)
            ->zAdd('buffer:feed', $feed->created_at->timestamp, $feed->uuid)
            ->exec();

        // dispatch the event for feed fanout process
        event(new FeedPosted($feed->uuid, $feed->created_at->timestamp));
    }

    /**
     * Push feed to each user's feed
     *
     * @param array $params
     */
    public function fanoutFeed($params = []) : void {
        $pageSize = 50;
        $cursor = 0;
        while(true) {
            //TODO: user id is not continuous...
            $users = Redis::zRange('users', $cursor, $cursor + $pageSize - 1);
            $cursor += $pageSize;

            $toPush = [];
            // no need to push to none exist feeds, as they are not active
            foreach($users as $user) {
                $exists = Redis::exists("user:$user:feed");
                if(!$exists) continue;
                $toPush[] = $user;
            }

            Redis::pipeline(function ($pipe) use ($toPush, $params) {
                foreach($toPush as $user) {
                    // making the score negative so that the timeline is desc
                    $pipe->zAdd("user:$user:feed", $params["ts"] * -1, $params["uuid"])
                        ->expire("user:$user:feed", $this->cacheTTL);
                }
            });

            if(count($users) < $pageSize) {
                break;
            }
        }
    }

    /**
     * Persist feed in cache into database
     */
    public function persist() : void {
        $now = Carbon::now()->timestamp;
        $threshold = $now - $this->bufferTimeout;
        $feeds = [];

        while(true) {
            $buffers = Redis::zPopMin('buffer:feed');

            if (empty($buffers)) {
                // break if nothing in the set
                break;
            }

            $break = false;
            foreach ($buffers as $uuid => $ts) {
                if ($ts > $threshold) {
                    // break if threshold is hit
                    $break = true;
                    // add the feed back
                    Redis::zAdd('buffer:feed', $ts, $uuid);
                } else {
                    $feed = Redis::hGetAll("feed:$uuid");
                    $feeds[] = $feed;
                }
            }

            if($break) break;
        }

        DB::beginTransaction();
        try {
            Feed::insert($feeds);
            Log::info("Persisted " . count($feeds) . " feeds into database");
            foreach($feeds as $feed) {
                Log::debug("Persisted " . $feed['uuid']);
            }
            DB::commit();
        } catch(\Exception $exception) {
            DB::rollBack();
            Log::Error("Failed to persist feed into database, error: " . $exception->getMessage());
        }
    }

    /**
     * Preload profile feed in cache
     * @param $userId
     * @param $count
     */
    public function preloadProfile($userId, $count): void
    {
        // TODO: Query db, build up cache
    }

    /**
     * Preload feed in cache
     * @param $userId
     * @param $count
     */
    public function preloadFeed($userId, $count): void
    {
        // TODO: Query db, build up cache
    }

    /**
     * Query cache first for feeds, if not found in cache, load from db
     *
     * @param array $uuids
     * @return array
     */
    private function findFeedByUuid(array $uuids) {
        $feeds = $remain = [];
        foreach($uuids as $uuid) {
            $cache = Redis::hGetAll("feed:$uuid");

            if(!empty($cache)) {
                $feeds[$uuid] = (new Feed())->fill($cache);
                continue;
            }
            $remain[] = $uuid;
        }

        $db = Feed::whereIn('uuid', $remain)
            ->OrderBy('created_at', 'desc')
            ->get();
        foreach($db as $data) {
            $feeds[$data->uuid] = $data;
            Redis::hMSet("feed:$data->uuid", $data->toArray());
            Redis::expire("feed:$data->uuid", $this->cacheTTL);
        }

        return $feeds;
    }

    /**
     * Decide the maxium number of data to preload into the cache
     * Stage 1, load 100 when offset+limit < 100
     * Stage 2, load 200 when 100 <= offset+limit < 200
     * Stage 3, load 400 when 200 <= offset+limit < 400
     * Stage 0, load from db. for simplicity when offset+limit >= 400
     *
     * @param int $offset
     * @param int $limit
     * @return int
     */
    private function getWarmUpDataCount(int $offset, int $limit)
    {
        $total = $offset + $limit;
        if($total < 100) {
            return 100;
        } else if ($total >= 100 && $total < 200) {
            return 200;
        } else if ($total >= 200 && $total < 400) {
            return 400;
        } else {
            return 0;
        }
    }
}
