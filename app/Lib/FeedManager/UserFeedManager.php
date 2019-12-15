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
}
