<?php


namespace App\Lib;


use App\Feed;
use Illuminate\Pagination\Paginator;

class TimeSeriesPaginator extends Paginator
{

    /**
     * TimeSeriesPaginator constructor.
     *
     * @param $items
     * @param $perPage
     * @throws \Exception
     */
    public function __construct($items, $perPage)
    {
        parent::__construct($items, $perPage, null, []);
        if($this->items->first() && ! $this->items->first() instanceof Feed) {
            throw new \Exception("Collection items has to be an instance of Feed");
        }
    }

    protected $pageName = 'time';
    protected $path = '';

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'data' => $this->items->toArray(),
            'from' => $this->timeFrom(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path,
            'per_page' => $this->perPage(),
            'to' => $this->timeTo(),
        ];
    }

    /**
     * Get the value of the first item
     *
     * @return int
     */
    public function firstItem()
    {
        return $this->items->first();
    }

    /**
     * Get the time of the first item
     *
     * @return mixed
     */
    public function timeFrom()
    {
        return $this->items->count() > 0 ? $this->items->first()->created_at : null;
    }

    /**
     * Get the value of the last item
     *
     * @return int
     */
    public function lastItem()
    {
        return $this->items->last();
    }

    /**
     * Get the time of the last item
     *
     * @return mixed
     */
    public function timeTo()
    {
        return $this->items->count() > 0 ? $this->items->last()->created_at : null;
    }

    /**
     * Get the URL for the next page.
     *
     * @return string|null
     */
    public function nextPageUrl()
    {
        return $this->url($this->timeTo() ? $this->timeTo()->getPreciseTimestamp(3) : 0);
    }

}
