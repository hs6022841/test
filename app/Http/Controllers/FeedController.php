<?php

namespace App\Http\Controllers;

use App\Feed;
use App\Lib\FeedStrategy\FeedContract;
use App\Lib\FeedSubscriber\FeedSubscriberContract;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Webpatser\Uuid\Uuid;

class FeedController extends Controller
{
    protected $feedService;
    protected $feedSubscriberService;
    protected $defaultPageSize = 10;

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
     * @param Request $request
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        // refresh the redis ttl
        $this->feedSubscriberService->register(Auth::id());

        $time=$request->get('time') ? Carbon::createFromTimestampMs($request->get('time')) : Carbon::now();
        $feeds = $this->feedService->getFeed(Auth::user(), $time, $this->defaultPageSize);

        return view('feed', ['feeds' => $feeds, 'next' => $feeds->nextPageUrl()]);
    }

    /**
     * Show the user profile.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function profile(Request $request)
    {
        $time=$request->get('time') ? Carbon::createFromTimestampMs($request->get('time')) : Carbon::now();
        $feeds = $this->feedService->getProfile(Auth::user(), $time, $this->defaultPageSize);

        return view('feed', ['feeds' => $feeds, 'next' => $feeds->nextPageUrl()]);
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

    /**
     * Delete a feed
     *
     * @param $id
     * @return mixed
     */
    public function destroy($id)
    {
        // delete
        $feed = $this->feedService->lookupFeed($id);
        if(Auth::id() == $feed->user_id) {
            $this->feedService->deleteFeed($feed);
            return response()->redirectTo('feed/profile')->with('success', 'Successfully deleted the feed!');
        } else {
            return response()->redirectTo('feed/profile')->with('error', 'Not allowed to delete the feed!');
        }
    }
}
