<?php

namespace Tests\Unit;

use App\User;
use Illuminate\Support\Facades\App;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubscribeToAllTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic unit test example.
     *
     * @return void
     */
    public function testExample()
    {
        User::create([
            'name' => 'test',
            'email' => 'test@test.com',
            'username' => '222',
            'password' => '123123',
        ]);
        $this->assertTrue(true);
    }
}
