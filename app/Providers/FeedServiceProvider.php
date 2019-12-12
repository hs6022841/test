<?php

namespace App\Providers;

use App\Lib\Feed\PushToAllStrategy;
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
        $this->app->bind('App\Lib\Feed\FeedContract', function () {
            $strategy = env('FEED_STRATEGY', 'push_to_all');
            switch($strategy) {
                case 'push_to_all':
                    return new PushToAllStrategy();
                default:
                    throw new \Exception('Undefined feed strategy!');
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
