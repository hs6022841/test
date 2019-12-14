<?php

use App\Feed;
use App\User;
use Illuminate\Database\Seeder;

class FeedTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(User::class, 20)->create()->each(function ($user) {
            $user->feeds()->createMany(
                factory(Feed::class, 10)->make()->toArray()
            );
        });
    }
}
