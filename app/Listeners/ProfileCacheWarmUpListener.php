<?php

namespace App\Listeners;

use App\Events\ProfileCacheWarmUp;
use App\Lib\Feed\FeedContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ProfileCacheWarmUpListener implements ShouldQueue
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
     * @param ProfileCacheWarmUp $event
     * @return bool
     */
    public function handle(ProfileCacheWarmUp $event)
    {
        $this->feedService->preloadProfile($event->userId, $event->count);

        // stop event propagation
        return false;
    }

    /**
     * Handle a job failure.
     *
     * @param ProfileCacheWarmUp $event
     * @param \Exception $exception
     * @return void
     */
    public function failed(ProfileCacheWarmUp $event, $exception)
    {
        Log::critical("Failed warmining up profile cache, error: " . $exception->getMessage());
    }
}
