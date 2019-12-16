<?php


namespace App\Lib;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;

class StorageBuffer
{
    protected $bufferTimeout;
    protected $insertKey;
    protected $deleteKey;
    protected $feedKey;
    protected $cacheTTL;
    protected $persistBatchSize = 50;

    public function __construct($key = '')
    {
        $this->insertKey = 'buffer:' . (empty($key) ? '' : ($key . ':')) . 'insert';
        $this->deleteKey = 'buffer:' . (empty($key) ? '' : ($key . ':')) . 'delete';
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
        Redis::zRem($this->insertKey, $id);
        Redis::zAdd($this->deleteKey, $score, $id);
        Redis::expire($this->insertKey, $this->cacheTTL);
        Redis::expire($this->deleteKey, $this->cacheTTL);
    }

    /**
     * Get the inserted feed
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
     * Get the deleted feed
     *
     * @return TimeSeriesCollection
     */
    public function getDeletedIds()
    {
        return Redis::zRange($this->deleteKey, 0, -1);
    }

    /**
     * fetch inserts and deletes from the buffer and persist them into db
     * note that transactions should be handled by called inside the closures
     *
     * @param \Closure $persistInsert
     * @param \Closure $persistDelete
     */
    public function persist(\Closure $persistInsert, \Closure $persistDelete = null)
    {
        $threshold = Carbon::now()->subSeconds(env('BUFFER_PERSIST_TIMEOUT'));

        $insertIds = new Collection();
        $time = $threshold;
        $limit = $this->persistBatchSize;

        while(true) {
            $ret = get_timeseries($this->insertKey, $time, $limit);
            if($ret->count() == 0) {
                break;
            }

            $time = $ret->timeTo();
            $insertIds = $ret->uuids()->merge($insertIds);
        }

        $persistInsert($insertIds);
        Redis::zRem($this->insertKey, ...$insertIds);

        if($persistDelete instanceof \Closure) {
            $deleteIds = new Collection();
            $time = $threshold;
            $limit = $this->persistBatchSize;
            while(true) {
                $ret = get_timeseries($this->deleteKey, $time, $limit);
                if($ret->count() == 0) {
                    break;
                }
                $time = $ret->timeTo();
                $deleteIds = $ret->uuids()->merge($deleteIds);
            }

            $persistDelete($deleteIds);
            Redis::zRem($this->deleteKey, ...$deleteIds);
            foreach($deleteIds as $deleteId) {
                Redis::del($this->feedKey.$deleteId);
            }
        }
    }
}
