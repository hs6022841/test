<?php


namespace App\Lib;


use App\Like;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class LikeManager
{
    protected $cacheTTL;
    protected $buffer;

    public function __construct()
    {
        $this->cacheTTL = env('CACHE_TTL', 60);
        $this->buffer = new StorageBuffer('like');
    }


    protected function getLikeKey($userId, $uuid) {
        return "user:$userId:like:$uuid";
    }

    protected function encodeLike($userId, $feedUuid) {
        return serialize([
            'user_id' => $userId,
            'feed_uuid' => $feedUuid,
        ]);
    }

    public function persist() : void
    {
        $this->buffer->persist(function($data) {
            $likes = [];
            foreach($data as $value) {
                $likes[] = unserialize($value);
            }
            Like::insert($likes);
        });
    }

    public function add(Authenticatable $actor, $uuid): void
    {
        $time = Carbon::now();

        Redis::multi()
            ->set($this->getLikeKey($actor->id, $uuid), 1)
            ->expire($this->getLikeKey($actor->id, $uuid), $this->cacheTTL);

        $this->buffer->add($this->encodeLike($actor->id, $uuid), $time);

        Redis::exec();
    }

    public function cache(Authenticatable $actor, $uuid, $state): void
    {
        Redis::multi()
            ->set($this->getLikeKey($actor->id, $uuid), $state)
            ->expire($this->getLikeKey($actor->id, $uuid), $this->cacheTTL);
        Redis::exec();
    }

    public function get(Authenticatable $user, TimeSeriesPaginator $feeds): array
    {
        DB::enableQueryLog();
        $feeds = $feeds->getItems()->pluck('uuid');

        // first hitting the cache
        Redis::multi();
        foreach($feeds as $feed) {
            Redis::get($this->getLikeKey($user->id, $feed));
        }
        $state = Redis::exec();
        $state = array_combine($feeds->toArray(), $state);

        // if cache key not exist, a false will be returned
        $notInCache = array_filter($state, function($item) {
            return $item == false;
        });
        $inCache = array_filter($state, function($item) {
            return $item != false;
        });

        if(count($notInCache) == 0) {
            // if all keys are hit on cache, return
            return $state;
        }

        // if there are any missing keys from the cache, then rebuild the cache
        $data = Like::where('user_id', $user->id)
            ->whereIn('feed_uuid', array_keys($notInCache))
            ->select('feed_uuid')
            ->get();

        foreach(DB::getQueryLog() as $log) {
            Log::info("Query: " . $log['query'] . ", completed in " . $log['time']);
        }

        $inDb = [];
        foreach($data as $item) {
            $inDb[] = $item['feed_uuid'];
        }

        foreach($notInCache as $uuid=>$item) {
            $state = in_array($uuid, $inDb) ? 1 : -1;
            // update cache
            $this->cache($user, $uuid, $state);
            // update self as well
            $notInCache[$uuid] = $state;
        }

        return array_merge($inCache, $notInCache);
    }
}
