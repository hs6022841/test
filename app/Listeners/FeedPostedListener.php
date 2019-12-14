<?php

namespace App\Listeners;

use App\Events\FeedPosted;
use App\Feed;
use App\Lib\Feed\FeedContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class FeedPostedListener implements ShouldQueue
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
     * @param FeedPosted $event
     * @return bool
     */
    public function handle(FeedPosted $event)
    {
        $feed = (new Feed())->fill($event->feed);
        $this->feedService->fanoutFeed($feed);

        // stop event propagation
        return false;
    }

    /**
     * Handle a job failure.
     *
     * @param FeedPosted $event
     * @param \Exception $exception
     * @return void
     */
    public function failed(FeedPosted $event, $exception)
    {
        Log::critical("Failed processing task, error: " . $exception->getMessage());
    }
}
