<?php


namespace App\Lib\FeedManager;


use App\Events\FeedCachePreloaded;
use App\Events\ProfileCachePreloaded;
use App\Feed;
use App\Lib\StorageBuffer;
use App\Lib\TimeSeriesCollection;
use App\Lib\TimeSeriesPaginator;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

abstract class ManagerBase
{
    protected $cacheTTL;
    protected $actor;
    protected $buffer;

    public function __construct(Authenticatable $actor)
    {
        $this->cacheTTL = env('CACHE_TTL', 60);
        $this->actor = $actor;
        $this->buffer = new StorageBuffer();
    }

    /**
     * Get the redis key
     *
     * @return string
     */
    abstract protected function key() : string;

    /**
     * Event fired when need to preload feed into cache
     * @param Authenticatable $user
     * @param Carbon $time
     * @return mixed
     */
    abstract protected function firePreloadEvent(Authenticatable $user, Carbon $time);

    /**
     * Defines how data is being populated
     *
     * @param $time
     * @param $limit
     * @return TimeSeriesCollection
     */
    abstract protected function dataSource($time, $limit) : TimeSeriesCollection;


    /**
     * Get cache key for a feed
     *
     * @param $uuid
     * @return string
     */
    protected function getFeedKey($uuid) {
        return "feed:$uuid";
    }

    /**
     * Get feed from cache and db
     *
     * @param Carbon|null $time
     * @param int $limit
     * @return TimeSeriesPaginator
     * @throws \Exception
     */
    public function get(Carbon $time = null, $limit = 50): TimeSeriesPaginator
    {
        $time = is_null($time) ? Carbon::now() : $time;
        $feeds = new Collection();
        $remaining = $limit;
        while($remaining > 0) {
            // factor is a multiplier so that when $remaining is small enough,
            // we will fetch $factor * $remaining $items, then take the first $remaining items
            // to save bunch of potential round trips of db/redis call
            $factor = $remaining <= 10 ? 10 : 1;
            $ret = $this->loadFeed($this->actor, $time, $factor * $remaining);
            if($ret->count() == 0) {
                break;
            }
            $ret = $this->findFeedByUuid($ret);
            $ret = $ret->slice(0, $remaining);
            if($ret->count() == 0) {
                break;
            }
            $feeds = $feeds->merge($ret);
            $remaining -= $ret->count();
            $time = $ret->last()->created_at;
        }
        return new TimeSeriesPaginator($feeds, $limit);
    }

    /**
     * preload feed into cache
     *
     * @param Carbon|null $time
     */
    public function preload(Carbon $time)
    {
        // FIXME: should have a mechanism to decide the preload size,
        // should probably based on how active the user is or how deep the provided time is
        // For now, load everything
        $time = Carbon::now();
        while(true) {
            $feeds = $this->dataSource($time, 100);
            if($feeds->count() == 0) {
                break;
            }
            $time = $feeds->timeTo();
            Redis::pipeline(function ($pipe) use ($feeds) {
                foreach($feeds as $uuid=>$time) {
                    $pipe->zAdd($this->key(), $time, $uuid)
                        ->expire($this->key(), $this->cacheTTL);
                }
            });
        }
    }

    protected function loadFeedFromDb(Carbon $time, $limit, $userId = null) {
//        DB::enableQueryLog();
        $feeds = Feed::select('uuid', 'created_at')
            ->where('created_at', '<', $time);
        if(!is_null($userId)) {
            $feeds = $feeds->where('user_id', $userId);
        }
        $feeds = $feeds->OrderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
//        Log::info("Fetching feeds from database, time: $time, limit: $limit, user_id: $userId");
//        foreach(DB::getQueryLog() as $log) {
//            Log::info("Query: " . $log['query'] . ", completed in " . $log['time']);
//        }
        return new TimeSeriesCollection($feeds);
    }


    /**
     * Load feed from both cache and db
     * if db is being hit, fire an event to preload data into cache
     *
     * @param Authenticatable $actor
     * @param Carbon $time
     * @param $limit
     * @return TimeSeriesCollection|Collection
     */
    protected function loadFeed(Authenticatable $actor, Carbon $time, $limit) {
        // fetched from cache
        $ret = get_timeseries($this->key(), $time, $limit);
        if($ret->count() >= $limit) {
            return $ret;
        }
        // fetch the reset from db
        $next = $ret->count() == 0 ? $time : $ret->timeTo();
        $limit = $limit - $ret->count();
        $dbRet = $this->dataSource($next, $limit);
        $ret = $ret->concat($dbRet);
        // if there are data returned, we start to warm up cache
        if(count($dbRet) != 0) {
            $this->firePreloadEvent($actor, $time);
        }

        return $ret;
    }

    /**
     * Query cache first for feeds, if not found in cache, load from db
     *
     * @param TimeSeriesCollection $uuids
     * @return Collection
     */
    protected function findFeedByUuid(TimeSeriesCollection $uuids) {
        $feeds = [];
        foreach($uuids as $uuid=>$time) {
            $cache = Redis::hGetAll($this->getFeedKey($uuid));

            if(!empty($cache)) {
                $feeds[$uuid] = (new Feed())->fill($cache);
                unset($uuids[$uuid]);
                continue;
            }
        }

        $db = Feed::whereIn('uuid', $uuids->uuids())
            ->OrderBy('created_at', 'desc')
            ->get();
        foreach($db as $data) {
            $feeds[$data->uuid] = $data;
            Redis::hMSet($this->getFeedKey($data->uuid), $data->toArray());
            Redis::expire($this->getFeedKey($data->uuid), $this->cacheTTL);
        }

        return collect(array_values($feeds));
    }
}
