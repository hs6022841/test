<?php


namespace App\Lib\FeedSubscriber;


use App\User;
use Illuminate\Support\Facades\Redis;

class SubscribeToAll implements FeedSubscriberContract
{
    protected $key;

    public function __construct()
    {
        $this->key = 'users';
    }

    /**
     * Initial following list for newly registered users
     *
     * all users will be following to each other, so no need to set the targetUsers during setup process
     *
     * @param $actorUserId
     * @param array $targetUserIds
     */
    public function setup($actorUserId, $targetUserIds = []) : void
    {
        // using sorted set here so that we can slice the set during the fanout process
        Redis::zAdd($this->key, $actorUserId, $actorUserId);
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
        $exist = Redis::exists($this->key);
        if(!$exist) {
            // for this case specifically, since we are pushing to all users, fetching the entire user table is necessary
            // though it should be paginated and load the cache in an async task
            $users = User::pluck('id');
            foreach($users as $user) {
                Redis::zAdd($this->key, $user, $user);
            }
        }
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
    public function fanoutToFollowers($actorId, \Closure $fanout): void
    {
        $pageSize = 50;
        $cursor = 0;
        while(true) {
            //TODO: user id is not continuous...
            $users = Redis::zRange($this->key, $cursor, $cursor + $pageSize - 1);
            $cursor += $pageSize;

            $fanout($users);

            if(count($users) < $pageSize) {
                break;
            }
        }
    }
}
