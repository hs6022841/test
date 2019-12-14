<?php


namespace App\Lib;

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class StorageBuffer
{
    protected $bufferTimeout;
    protected $key;

    public function __construct()
    {
        $this->key = 'buffer:feed';
        $this->bufferTimeout = env('BUFFER_PERSIST_TIMEOUT', 10);
    }

    public function add($id, $score)
    {
        Redis::zAdd($this->key, $score, $id);
    }

    public function delete($id)
    {
        Redis::zRem($this->key, $id);
    }

    public function popMin()
    {
        return Redis::zPopMin($this->key);
    }

    public function get($offset, $limit)
    {
        return Redis::zRange($this->key, $offset, $limit);
    }

    public function persist(\Closure $persistToDb)
    {
        $now = Carbon::now()->timestamp;
        $threshold = $now - $this->bufferTimeout;
        $ids = [];

        while(true) {
            $buffer = $this->popMin();

            if (empty($buffer)) {
                // break if nothing in the set
                break;
            }

            $break = false;
            foreach ($buffer as $id => $score) {
                if ($score > $threshold) {
                    // break if threshold is hit
                    $break = true;
                    // add the feed back
                    $this->add($id, $score);
                } else {
                    $ids[] = $id;
                }
            }

            if($break) break;
        }

        $persistToDb($ids);
    }
}
