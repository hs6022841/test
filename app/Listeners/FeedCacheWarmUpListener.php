<?php

namespace App\Listeners;

use App\Events\FeedCacheWarmUp;
use App\Lib\Feed\FeedContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class FeedCacheWarmUpListener implements ShouldQueue
{
    protected $feedService;

    /**
     * Create the event listener.
     *
     * @param FeedContract $feedContract
     */
    public function __construct(FeedContract $feedContract)
    {
        $this->feedService = $feedContract;
    }

    /**
     * Handle the event.
     *
     * @param FeedCacheWarmUp $event
     * @return bool
     */
    public function handle(FeedCacheWarmUp $event)
    {
        $this->feedService->preloadFeed($event->userId, $event->time);

        // stop event propagation
        return false;
    }

    /**
     * Handle a job failure.
     *
     * @param FeedCacheWarmUp $event
     * @param \Exception $exception
     * @return void
     */
    public function failed(FeedCacheWarmUp $event, $exception)
    {
        Log::critical("Failed warmining up feed cache, error: " . $exception->getMessage());
    }
}
