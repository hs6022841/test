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
    public $isInsert;

    /**
     * Create a new event instance.
     *
     * @param Feed $feed
     * @param bool $isInsert
     */
    public function __construct($feed, $isInsert = true)
    {
        $this->feed = $feed;
        $this->isInsert = $isInsert;
    }
}
