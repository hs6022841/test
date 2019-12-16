<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes([
    'register' => false,
    'reset' => false,
    'verify' => false,
]);

Route::get('/feed/profile', 'FeedController@profile')->name('feed.profile');
// all using get for easier testing
Route::get('/feed/like/{id}', 'FeedController@like')->name('feed.like');
Route::get('/feed/delete/{id}', 'FeedController@destroy')->name('feed.delete');
Route::resource('/feed', 'FeedController');
