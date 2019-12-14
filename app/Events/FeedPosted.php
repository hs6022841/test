<?php

namespace App\Events;

use App\Feed;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class FeedPosted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $feed;

    /**
     * Create a new event instance.
     *
     * @param Feed $feed
     */
    public function __construct($feed)
    {
        $this->feed = $feed;
    }
}
