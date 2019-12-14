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

    /**
     * fetch inserts and deletes from the buffer and persist them into db
     * note that transactions should be handled by called inside the closures
     *
     * @param \Closure $persistInsert
     * @param \Closure $persistDelete
     */
    public function persist(\Closure $persistInsert, \Closure $persistDelete)
    {
        $threshold = Carbon::now()->subMinutes(env('BUFFER_PERSIST_TIMEOUT'))->timestamp;
        $insertIds = [];
        $deleteIds = [];

        $offset = 0;
        $limit = 50;

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
            $insertIds = array_merge($insertIds, $ret);
        }

        $offset = 0;
        $limit = 50;
        while(true) {
            $pagination = [
                'limit' => [
                    'offset' => $offset,
                    'count' => $limit,
                ]
            ];

            $ret = Redis::zRevRangeByScore($this->deleteKey, $threshold, '-inf', $pagination);
            if(empty($ret)) {
                break;
            }

            $offset += $limit;
            $deleteIds = array_merge($deleteIds, $ret);
        }

        $persistInsert($insertIds);
        $persistDelete($deleteIds);

        Redis::zRem($this->insertKey, ...$insertIds);
        Redis::zRem($this->deleteKey, ...$deleteIds);
    }
}
