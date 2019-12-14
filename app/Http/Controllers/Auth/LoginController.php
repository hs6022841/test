<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Lib\FeedSubscriber\FeedSubscriberContract;
use App\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Faker\Factory as Faker;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    protected $feedSubscriberService;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/feed';

    /**
     * Create a new controller instance.
     *
     * @param FeedSubscriberContract $feedSubscriberService
     */
    public function __construct(FeedSubscriberContract $feedSubscriberService)
    {
        $this->feedSubscriberService = $feedSubscriberService;
        $this->middleware('guest')->except('logout');
    }

    /**
     * Overriding AuthenticatesUsers->username
     *
     * @return string
     */
    public function username()
    {
        return 'username';
    }

    /**
     * Overriding AuthenticatesUsers->validateLogin
     *
     * @param Request $request
     */
    protected function validateLogin(Request $request)
    {
        $request->validate([
            $this->username() => 'required|string',
        ]);
    }

    /**
     * Overriding AuthenticatesUsers->attemptLogin
     *
     * @param Request $request
     * @return bool
     */
    protected function attemptLogin(Request $request)
    {
        // if user does not exists
        if(!$this->guard()->attempt(
            $this->credentials($request), $request->filled('remember')
        )) {
            Log::info("User " . $request->{$this->username()}. " does not exist, creating new user");

            event(new Registered($user = $this->create($request->all())));

            // Executing feed setup for newly registered user synchronously
            $this->feedSubscriberService->setup($user->id);

            $this->guard()->login($user);
        }
        return true;
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
    protected function create(array $data)
    {
        return User::create([
            'username' => $data['username'],
            'name' => Faker::create()->name,
            // dummy data
            'password' => Hash::make(''),
        ]);
    }
}
