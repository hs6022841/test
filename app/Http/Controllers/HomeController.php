<?php

namespace App\Http\Controllers;

use App\Lib\Feed\FeedContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    protected $feedService;

    /**
     * Create a new controller instance.
     *
     * @param FeedContract $feedContract
     */
    public function __construct(FeedContract $feedContract)
    {
        $this->feedService = $feedContract;
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $this->feedService->follow(Auth::id(), 1);
        return view('home');
    }
}
