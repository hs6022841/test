<?php


namespace App\Lib;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class StorageBuffer
{
    protected $bufferTimeout;
    protected $insertKey;
    protected $deleteKey;
    protected $feedKey;
    protected $cacheTTL;

    public function __construct()
    {
        $this->insertKey = 'buffer:insert';
        $this->deleteKey = 'buffer:delete';
        $this->feedKey = 'feed:';
        $this->bufferTimeout = env('BUFFER_PERSIST_TIMEOUT', 10);
        $this->cacheTTL = env('CACHE_TTL', 60);
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
        Redis::expire($this->insertKey, $this->cacheTTL);
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
            ->del($this->feedKey . $id)
            ->zAdd($this->deleteKey, $score, $id)
            ->expire($this->deleteKey, $this->cacheTTL)
            ->exec();
        Redis::zRem($this->insertKey, $id);
    }

    /**
     * Get the feeds inside buffer
     *
     * @param Carbon $time
     * @param $limit
     * @return TimeSeriesCollection
     */
    public function get(Carbon $time, $limit)
    {
        return get_timeseries($this->insertKey, $time, $limit);
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
        $threshold = Carbon::now()->subSeconds(env('BUFFER_PERSIST_TIMEOUT'));

        $insertIds = new Collection();
        $time = $threshold;
        $limit = 50;

        while(true) {
            $ret = get_timeseries($this->insertKey, $time, $limit);
            if($ret->count() == 0) {
                break;
            }

            $time = $ret->timeTo();
            $insertIds = $ret->uuids()->merge($insertIds);
        }

        $time = $threshold;
        $limit = 50;
        $deleteIds = new Collection();
        while(true) {
            $ret = get_timeseries($this->deleteKey, $time, $limit);
            if($ret->count() == 0) {
                break;
            }
            $time = $ret->timeTo();
            $deleteIds = $ret->uuids()->merge($deleteIds);
        }

        $persistInsert($insertIds);
        $persistDelete($deleteIds);

        Redis::zRem($this->insertKey, ...$insertIds);
        Redis::zRem($this->deleteKey, ...$deleteIds);
    }
}
