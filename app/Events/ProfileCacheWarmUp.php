<?php

namespace App\Events;

use Carbon\Carbon;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class ProfileCacheWarmUp
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $time;

    /**
     * Create a new event instance.
     *
     * @param $userId
     * @param $time
     */
    public function __construct($userId, Carbon $time)
    {
        $this->userId = $userId;
        $this->time = $time;
    }
}
