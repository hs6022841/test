<?php


namespace App\Lib\FeedManager;

use App\Events\ProfileCachePreloaded;
use App\Lib\TimeSeriesCollection;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;

class ProfileFeedManager extends ManagerBase
{
    /**
     * @inheritDoc
     */
    protected function key(): string
    {
        return "user:{$this->actor->id}:profile";
    }

    /**
     * @inheritDoc
     */
    protected function firePreloadEvent(Authenticatable $user, Carbon $time)
    {
        event(new ProfileCachePreloaded($user, $time));
    }

    /**
     * @inheritDoc
     */
    protected function dataSource($time, $limit): TimeSeriesCollection
    {
        return $this->loadFeedFromDb($time, $limit, $this->actor->id);
    }
}
