<?php


namespace App\Lib\FeedStrategy;


use App\Feed;
use App\Lib\FeedSubscriber\FeedSubscriberContract;
use App\Lib\StorageBuffer;
use Illuminate\Support\Collection;
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

    protected function persistInsertion(Collection $uuids)
    {
        $feeds = [];
        foreach($uuids as $uuid) {
            $feeds[] = Redis::hGetAll($this->getFeedKey($uuid));
        }

        Log::info("Persisting " . $uuids->count() . " feeds into database");

        $ret = Feed::insert($feeds);
        if(!$ret) {
            Log::Error("Failed to persist feeds into database, feeds: " . json_encode($feeds));
            return;
        }
        Log::info("Persisted " . $uuids->count() . " feeds into database");
    }

    protected function persistDeletion(Collection $uuids) {

        Log::info("Deleting " . $uuids->count() . " feeds from database");

        Feed::whereIn('uuid', $uuids)->delete();

        Log::info("Deleted " . $uuids->count() . " feeds from database");
    }


}
