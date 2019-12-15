<?php

namespace App\Providers;

use App\Events\FeedCachePreloaded;
use App\Events\ProfileCachePreloaded;
use App\Events\FeedPosted;
use App\Listeners\FeedCachePreloadedListener;
use App\Listeners\ProfileCachePreloadedListener;
use App\Listeners\FeedPostedListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;


class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        FeedPosted::class => [
            FeedPostedListener::class,
        ],
        ProfileCachePreloaded::class => [
            ProfileCachePreloadedListener::class,
        ],
        FeedCachePreloaded::class => [
            FeedCachePreloadedListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
