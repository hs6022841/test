<?php


namespace App\Lib\FeedManager;

use App\Events\FeedCachePreloaded;
use App\Lib\TimeSeriesCollection;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;

class UserFeedManager extends ManagerBase
{
    /**
     * @inheritDoc
     */
    protected function key(): string
    {
        return "user:{$this->actor->id}:feed";
    }

    /**
     * @inheritDoc
     */
    protected function firePreloadEvent(Authenticatable $user, Carbon $time)
    {
        event(new FeedCachePreloaded($user, $time));
    }

    /**
     * @inheritDoc
     */
    protected function dataSource($time, $limit): TimeSeriesCollection
    {
        return $this->loadFeedFromDb($time, $limit);
    }

    /**
     * Load feed from both cache and db
     * if db is being hit, fire an event to preload data into cache
     *
     * @param Authenticatable $actor
     * @param Carbon $time
     * @param $limit
     * @return TimeSeriesCollection
     */
    protected function loadCombinedFeed(Authenticatable $actor, Carbon $time, $limit) {
        // fetch from the buffer
        $ret = $this->buffer->get($time, $limit);
        if($ret->count() >= $limit) {
            return $ret;
        }

        // fetched from cache and db
        $limit = $limit - $ret->count();
        $next = $ret->count() == 0 ? $time : $ret->timeTo();

        $ret1 = parent::loadCombinedFeed($actor, $next, $limit);
        $ret = $ret->concat($ret1);
        return $ret;
    }
}
