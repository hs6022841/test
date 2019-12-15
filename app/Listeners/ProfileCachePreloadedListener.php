<?php

namespace App\Listeners;

use App\Events\ProfileCachePreloaded;
use App\Lib\FeedStrategy\FeedContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ProfileCachePreloadedListener implements ShouldQueue
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
     * @param ProfileCachePreloaded $event
     * @return bool
     */
    public function handle(ProfileCachePreloaded $event)
    {
        $this->feedService->preloadProfile($event->user, $event->time);

        // stop event propagation
        return false;
    }

    /**
     * Handle a job failure.
     *
     * @param ProfileCachePreloaded $event
     * @param \Exception $exception
     * @return void
     */
    public function failed(ProfileCachePreloaded $event, $exception)
    {
        Log::critical("Failed warmining up profile cache, error: " . $exception->getMessage());
    }
}
