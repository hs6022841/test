<?php

namespace App\Events;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class ProfileCachePreloaded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $time;

    /**
     * Create a new event instance.
     *
     * @param $userId
     * @param $time
     */
    public function __construct(Authenticatable $user, Carbon $time)
    {
        $this->user = $user;
        $this->time = $time;
    }
}
