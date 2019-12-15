<?php

namespace App\Providers;

use App\Lib\FeedStrategy\FeedContract;
use App\Lib\FeedStrategy\PushStrategy;
use App\Lib\FeedSubscriber\FeedSubscriberContract;
use App\Lib\FeedSubscriber\SubscribeToAll;
use Illuminate\Support\ServiceProvider;

class FeedServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(FeedSubscriberContract::class, function () {
            $strategy = env('FEED_SUBSCRIBER', 'all');
            switch($strategy) {
                case 'all':
                    return new SubscribeToAll();
                default:
                    throw new \Exception('Undefined feed subscriber strategy!');
            }
        });

        $this->app->bind(FeedContract::class, function ($service) {
            $strategy = env('FEED_FANOUT_STRATEGY', 'push');
            switch($strategy) {
                case 'push':
                    return new PushStrategy($service->make(FeedSubscriberContract::class));
                default:
                    throw new \Exception('Undefined feed fanout strategy!');
            }
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
