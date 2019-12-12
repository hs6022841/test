<?php

namespace App\Events;

use App\Feed;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log;

class FeedPosted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $uuid;
    public $ts;

    /**
     * Create a new event instance.
     *
     * @param $uuid
     * @param $ts
     */
    public function __construct($uuid, $ts)
    {
        $this->uuid = $uuid;
        $this->ts = $ts;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('feed-create');
    }
}
