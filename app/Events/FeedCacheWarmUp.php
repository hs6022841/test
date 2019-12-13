<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class FeedCacheWarmUp
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $count;

    /**
     * Create a new event instance.
     *
     * @param $userId
     * @param $count
     */
    public function __construct($userId, $count)
    {
        $this->userId = $userId;
        $this->count = $count;
    }
}
