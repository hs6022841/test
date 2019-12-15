<?php


namespace App\Lib;

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class StorageBuffer
{
    protected $bufferTimeout;
    protected $insertKey;
    protected $deleteKey;

    public function __construct()
    {
        $this->insertKey = 'buffer:insert';
        $this->deleteKey = 'buffer:delete';
        $this->bufferTimeout = env('BUFFER_PERSIST_TIMEOUT', 10);
    }

    /**
     * Add into insert buffer
     *
     * @param $id
     * @param Carbon $time
     */
    public function add($id, Carbon $time)
    {
        $score = $time->getPreciseTimestamp(3);
        Redis::zAdd($this->insertKey, $score, $id);
    }

    /**
     * Add into delete buffer
     * Also remove it from the insert buffer
     *
     * @param $id
     * @param Carbon $time
     */
    public function delete($id, Carbon $time)
    {
        $score = $time->getPreciseTimestamp(3);
        Redis::multi()
            ->zAdd($this->deleteKey, $score, $id)
            ->zRem($this->insertKey, $id)
            ->exec();
    }

    /**
     * fetch inserts and deletes from the buffer and persist them into db
     * note that transactions should be handled by called inside the closures
     *
     * @param \Closure $persistInsert
     * @param \Closure $persistDelete
     */
    public function persist(\Closure $persistInsert, \Closure $persistDelete)
    {
        $threshold = Carbon::now()->subMinutes(env('BUFFER_PERSIST_TIMEOUT'));

        $insertIds = [];
        $time = $threshold;
        $limit = 2;

        while(true) {
            $ret = get_timeseries($this->insertKey, $time, $limit);
            if($ret->count() == 0) {
                break;
            }

            $time = Carbon::createFromTimestampMs($ret->toTime());
            $insertIds = array_merge($insertIds, $ret->items());
        }

        $time = $threshold;
        $limit = 2;
        $deleteIds = [];
        while(true) {
            $ret = get_timeseries($this->deleteKey, $time, $limit);
            if($ret->count() == 0) {
                break;
            }
            $time = Carbon::createFromTimestampMs($ret->toTime());
            $deleteIds = array_merge($deleteIds, $ret->items());
        }

        $persistInsert($insertIds);
        $persistDelete($deleteIds);

        Redis::zRem($this->insertKey, ...$insertIds);
        Redis::zRem($this->deleteKey, ...$deleteIds);
    }
}
