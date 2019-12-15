<?php


namespace App\Lib\Feed;


use App\Feed;
use App\Lib\FeedSubscriber\FeedSubscriberContract;
use App\Lib\StorageBuffer;
use App\Lib\TimeSeriesPaginator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

abstract class StrategyBase
{

    protected $cacheTTL;
    protected $buffer;
    protected $feedSubscriberService;

    public function __construct(FeedSubscriberContract $feedSubscriberService)
    {
        $this->cacheTTL = env('CACHE_TTL', 60);
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

    protected function loadFeedFromDb(Carbon $time, $limit, $userId = null) {
       $feeds = Feed::select('uuid', 'created_at')
        ->where('created_at', '<', $time);
        if(!is_null($userId)) {
            $feeds = $feeds->where('user_id', $userId);
        }
        $feeds = $feeds->OrderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $result = [];
        foreach($feeds as $feed) {
            $result[$feed['uuid']] = $feed['created_at']->getPreciseTimestamp(3);
        }

        return new TimeSeriesPaginator($result, $limit);
    }

    /**
     * Query cache first for feeds, if not found in cache, load from db
     *
     * @param array $uuids
     * @return array
     */
    protected function findFeedByUuid(array $uuids) {
        $feeds = [];
        foreach($uuids as $key=>$uuid) {
            $cache = Redis::hGetAll($this->getFeedKey($uuid));

            if(!empty($cache)) {
                $feeds[$uuid] = (new Feed())->fill($cache);
                unset($uuids[$key]);
                continue;
            }
        }

        $db = Feed::whereIn('uuid', $uuids)
            ->OrderBy('created_at', 'desc')
            ->get();
        foreach($db as $data) {
            $feeds[$data->uuid] = $data;
            Redis::hMSet($this->getFeedKey($data->uuid), $data->toArray());
            Redis::expire($this->getFeedKey($data->uuid), $this->cacheTTL);
        }

        return $feeds;
    }

    protected function addFeedsToTargets($userIds, Feed $feed)
    {
        if(!is_array($userIds)) {
            $userIds = [$userIds];
        }
        Redis::pipeline(function ($pipe) use ($userIds, $feed) {
            foreach($userIds as $userId) {
                // making the score negative so that the timeline is desc
                $pipe->zAdd($this->getUserFeedKey($userId), $feed->created_at->getPreciseTimestamp(3), $feed->uuid)
                    ->expire($this->getUserFeedKey($userId), $this->cacheTTL);
            }
        });
    }

    protected function persistInsertion(array $uuids)
    {
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
        Log::info("Persisted " . count($feeds) . " feeds into database");
    }

    protected function persistDeletion(array $uuids) {

        Log::info("Deleting " . count($uuids) . " feeds from database");

        Feed::whereIn('uuid', $uuids)->delete();

        Log::info("Deleted " . count($uuids) . " feeds from database");
    }
}
