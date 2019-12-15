<?php

namespace Tests\Unit;

use App\Feed;
use App\Lib\TimeSeriesPaginator;
use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TimeSeriesPaginatorTest extends TestCase
{
    use RefreshDatabase;
    /**
     * @return void
     * @throws \Exception
     */
    public function testPagination()
    {
        factory(User::class)->create([
            'id' => 1
        ]);
        $feeds = factory(Feed::class, 5)->make();

        $paginator = new TimeSeriesPaginator($feeds, 10);

        $this->assertEquals($feeds[0]->created_at->timestamp, $paginator->timeFrom()->timestamp, 'from time should equal to the created_at of the first item');
        $this->assertEquals($feeds[0]->uuid, $paginator->firstItem()->uuid, 'first item in paginator should equal to the first item passed in');
        $this->assertEquals($feeds[4]->created_at->timestamp, $paginator->timeTo()->timestamp, 'to time should equal to the created_at of the last item');
        $this->assertEquals($feeds[4]->uuid, $paginator->lastItem()->uuid, 'last item in paginator should equal to the last item passed in');
        $this->assertEquals(10, $paginator->perPage(), 'per page should be 10');
    }
}
