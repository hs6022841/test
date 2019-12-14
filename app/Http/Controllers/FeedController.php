<?php

namespace App\Http\Controllers;

use App\Feed;
use App\Lib\Feed\FeedContract;
use App\Lib\FeedSubscriber\FeedSubscriberContract;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Webpatser\Uuid\Uuid;

class FeedController extends Controller
{
    protected $feedService;
    protected $feedSubscriberService;

    /**
     * Create a new controller instance.
     *
     * @param FeedContract $feedService
     * @param FeedSubscriberContract $feedSubscriberService
     */
    public function __construct(FeedContract $feedService, FeedSubscriberContract $feedSubscriberService)
    {
        $this->feedService = $feedService;
        $this->feedSubscriberService = $feedSubscriberService;
        $this->middleware('auth');
    }

    /**
     * Show the feed list.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        // preload the followers to warm up the cache
        $this->feedSubscriberService->loadFollowers(Auth::id());

        $feeds = $this->feedService->fetchFeed(Auth::id());

        return view('feed', ['feeds' => $feeds]);
    }

    /**
     * Show the user profile.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function profile()
    {
        $feeds = $this->feedService->fetchProfileFeed(Auth::id());

        return view('feed', ['feeds' => $feeds]);
    }

    /**
     * Show the create feed page.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function create()
    {
        return view('feed-create');
    }

    /**
     * create a feed.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Support\Renderable
     * @throws \Exception
     */
    public function store(Request $request)
    {
        $request->validate([
            'comment' => 'required|string|max:255',
        ]);

        // Creating the feed model but not saving it
        $feed = (new Feed())->fill(array_merge($request->all(), [
            'user_id' => Auth::id(),
            'uuid' => (string) Uuid::generate(1),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]));

        $this->feedService->postFeed($feed);
        return view('feed-create');
    }
}
