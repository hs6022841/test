<?php

use App\Lib\TimeSeriesCollection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

function get_timeseries($key, Carbon $time, $limit)
{
    $pagination = [
        'withscores' => true,
        'limit' => [
            'offset' => 0,
            'count' => $limit,
        ]
    ];
    $score = $time->getPreciseTimestamp(3);

    // parenthesis is for excluding $score
    $ret = Redis::zRevRangeByScore($key, "($score"  , '-inf', $pagination);
    return new TimeSeriesCollection($ret);
}
