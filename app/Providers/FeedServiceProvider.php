<?php

namespace App\Providers;

use App\Lib\Feed\BasicStrategy;
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
            $strategy = env('FEED_STRATEGY', 'basic');
            switch($strategy) {
                case 'basic':
                    return new BasicStrategy();
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
