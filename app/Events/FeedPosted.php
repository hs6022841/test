<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

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
}
