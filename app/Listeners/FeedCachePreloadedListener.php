<?php

namespace App\Listeners;

use App\Events\FeedCachePreloaded;
use App\Lib\FeedStrategy\FeedContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class FeedCachePreloadedListener implements ShouldQueue
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
     * @param FeedCachePreloaded $event
     * @return bool
     */
    public function handle(FeedCachePreloaded $event)
    {
        $this->feedService->preloadFeed($event->user, $event->time);

        // stop event propagation
        return false;
    }

    /**
     * Handle a job failure.
     *
     * @param FeedCachePreloaded $event
     * @param \Exception $exception
     * @return void
     */
    public function failed(FeedCachePreloaded $event, $exception)
    {
        Log::critical("Failed warmining up feed cache, error: " . $exception->getMessage());
    }
}
