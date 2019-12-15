<?php
namespace App\Lib;

use App\Feed;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TimeSeriesCollection extends Collection
{
    public function __construct($items = [])
    {
        if($items instanceof Collection && $items->first() && $items->first() instanceof Feed) {
            // results from db, hence a feed collection
            $result = [];
            foreach($items as $item) {
                $result[$item['uuid']] = $item['created_at']->getPreciseTimestamp(3);
            }
            $items = $result;
        }
        parent::__construct($items);
    }

    public function getLimit() {
        return $this->limit;
    }

    public function timeFrom() {
        return Carbon::createFromTimestampMs($this->first());
    }

    public function timeTo() {
        return Carbon::createFromTimestampMs($this->last());
    }

    public function concat($source)
    {
        $items = array_merge($this->items, $source->items);

        return new TimeSeriesCollection($items);
    }

    public function uuids()
    {
        return $this->keys();
    }

    public function timeseries()
    {
        return $this->items;
    }
}
