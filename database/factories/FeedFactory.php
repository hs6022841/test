<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Feed;
use Faker\Generator as Faker;

$factory->define(Feed::class, function (Faker $faker) {
    return [
        'user_id' => 1,
        'uuid' => $faker->uuid,
        'comment' => $faker->text(),
        'created_at' => \Carbon\Carbon::now(),
        'updated_at' => \Carbon\Carbon::now(),
    ];
});
