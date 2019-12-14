<?php


namespace App\Lib\Feed;


use App\Feed;
use App\Lib\FeedSubscriber\FeedSubscriberContract;
use App\Lib\StorageBuffer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

abstract class StrategyBase
{

    protected $cacheTTL;
    protected $buffer;
    protected $feedSubscriberService;

    public function __construct(FeedSubscriberContract $feedSubscriberService)
    {
        $this->cacheTTL = env('FEED_CACHE_TTL', 60);
        $this->buffer = new StorageBuffer();
        $this->feedSubscriberService = $feedSubscriberService;
    }

    protected function getFeedKey($uuid) {
        return "feed:$uuid";
    }

    protected function getUserFeedKey($userId) {
        return "user:$userId:feed";
    }

    protected function getProfileKey($userId) {
        return "user:$userId:profile";
    }

    protected function loadFeedFromDb($offset, $limit) {
        return Feed::OrderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->pluck('uuid')
            ->toArray();
    }

    protected function loadProfileFromDb($actorUserId, $offset, $limit) {
        return Feed::where('user_id', $actorUserId)
            ->OrderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->pluck('uuid')
            ->toArray();
    }

    /**
     * Query cache first for feeds, if not found in cache, load from db
     *
     * @param array $uuids
     * @return array
     */
    protected function findFeedByUuid(array $uuids) {
        $feeds = $remain = [];
        foreach($uuids as $uuid) {
            $cache = Redis::hGetAll($this->getFeedKey($uuid));

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
            Redis::hMSet($this->getFeedKey($data->uuid), $data->toArray());
            Redis::expire($this->getFeedKey($data->uuid), $this->cacheTTL);
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
    protected function getWarmUpSize(int $offset, int $limit)
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

    protected function saveFeedToDb(array $uuids) {
        $feeds = [];
        foreach($uuids as $uuid) {
            $feeds[] = Redis::hGetAll($this->getFeedKey($uuid));
        }

        Log::info("Persisting " . count($feeds) . " feeds into database");

        $ret = Feed::insert($feeds);
        if(!$ret) {
            Log::Error("Failed to persist feeds into database, feeds: " . json_encode($feeds));
            return;
        }
        Log::info("Persisted" . count($feeds) . " feeds into database");
    }

}
