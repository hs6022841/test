<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;

class Feed extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'uuid', 'comment', 'created_at', 'updated_at'
    ];

    protected $hidden = ['id'];

    /**
     * Feed belongs to an user
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function getUserFeedKey($userId) {
        return "user:$userId:feed";
    }

    /**
     * Prepend this feed into provided users' feed buffer
     *
     * @param $userIds
     */
    public function attachToUser($userIds)
    {
        if(!is_array($userIds)) {
            $userIds = [$userIds];
        }
        Redis::pipeline(function ($pipe) use ($userIds) {
            foreach($userIds as $userId) {
                // making the score negative so that the timeline is desc
                $pipe->zAdd($this->getUserFeedKey($userId), $this->created_at->getPreciseTimestamp(3), $this->uuid)
                    ->expire($this->getUserFeedKey($userId), env('CACHE_TTL', 60));
            }
        });
    }
}
