<?php


namespace App\Lib;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Webpatser\Uuid\Uuid;

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
     * @param $score
     */
    public function add($id, $score)
    {
        Redis::zAdd($this->insertKey, $score, $id);
    }

    /**
     * Add into delete buffer
     * Also remove it from the insert buffer
     *
     * @param $id
     * @param $score
     * @throws \Exception
     */
    public function delete($id, $score)
    {
        Redis::multi()
            ->zAdd($this->deleteKey, $score, $id)
            ->zRem($this->insertKey, $id)
            ->exec();
    }

    public function get($offset, $limit)
    {
        $pagination = [
            'limit' => [
                'offset' => $offset,
                'count' => $limit,
            ]
        ];

        return Redis::zRevRangeByScore($this->insertKey, '+inf' , '-inf', $pagination);
    }

    public function persist(\Closure $persistToDb)
    {
        $threshold = Carbon::now()->subMinutes(env('BUFFER_PERSIST_TIMEOUT'))->timestamp;
        $ids = [];

        $offset = 0;
        $limit = 2;

        while(true) {
            $pagination = [
                'limit' => [
                    'offset' => $offset,
                    'count' => $limit,
                ]
            ];

            $ret = Redis::zRevRangeByScore($this->insertKey, $threshold, '-inf', $pagination);
            if(empty($ret)) {
                break;
            }

            $offset += $limit;
            $ids = array_merge($ids, $ret);
        }

        $persistToDb($ids);
    }
}
