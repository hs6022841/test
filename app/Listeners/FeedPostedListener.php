<?php

namespace App\Listeners;

use App\Events\FeedPosted;
use App\Feed;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class FeedPostedListener implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param FeedPosted $event
     * @return bool
     */
    public function handle(FeedPosted $event)
    {
        $pageSize = 50;
        $cursor = 0;
        while(true) {
            $users = Redis::zRange('users', $cursor, $cursor + $pageSize - 1);
            $cursor += $pageSize;

            Redis::pipeline(function ($pipe) use ($users, $event) {
                foreach($users as $user) {
                    // making the score negative so that the timeline is desc
                    $pipe->zAdd("user-feed:$user", $event->ts * -1, $event->uuid);
                }
            });

            if(count($users) < $pageSize) {
                break;
            }
        }


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
