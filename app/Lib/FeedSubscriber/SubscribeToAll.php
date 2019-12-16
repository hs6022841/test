<?php


namespace App\Lib\FeedSubscriber;


use Illuminate\Support\Facades\Redis;

class SubscribeToAll implements FeedSubscriberContract
{
    protected $key;
    protected $cacheTTL;

    public function __construct()
    {
        $this->key = 'users';
        $this->cacheTTL = env('CACHE_TTL', 60);
    }

    /**
     * all users will be following to each other, so no need to set the targetUsers during setup process
     *
     * @inheritDoc
     */
    public function register($actorUserId, $targetUserIds = []) : void
    {
        // using sorted set here so that we can slice the set during the fanout process
        Redis::zAdd($this->key, $actorUserId, $actorUserId);
        Redis::expire($this->key, $this->cacheTTL);
    }


    /**
     * @inheritDoc
     */
    public function deregister($actorUserId) : void
    {
        Redis::zRem($this->key, $actorUserId);
        Redis::expire($this->key, $this->cacheTTL);
    }

    /**
     * @inheritDoc
     */
    public function follow($actorUserId, $targetUserId): void
    {
        // TODO: Implement follow() method.
    }

    /**
     * @inheritDoc
     */
    public function unfollow($actorUserId, $targetUserId): void
    {
        // TODO: Implement unfollow() method.
    }

    /**
     * @inheritDoc
     */
    public function loadFollowers($actorUserId): void
    {
        // TODO: Implement loadFollowee() method.
    }

    /**
     * @inheritDoc
     */
    public function loadFollowee($actorUserId): void
    {
        // TODO: Implement loadFollowee() method.
    }

    /**
     * @inheritDoc
     */
    public function fanoutToFollowers($actorId, \Closure $fanout, $pageSize = 50): void
    {
        $cursor = 0;
        while(true) {
            $users = Redis::zRange($this->key, $cursor, $cursor + $pageSize - 1);
            $cursor += $pageSize;

            $fanout($users);

            if(count($users) < $pageSize) {
                break;
            }
        }
    }
}
