<?php


namespace App\Lib;


use Illuminate\Pagination\Paginator;

class TimeSeriesPaginator extends Paginator
{

    // holds the data <-> $ts mapping
    protected $mapping = [];
    protected $pageName = 'last';

    /**
     * TimeSeriesPaginator constructor.
     *
     * @param array $items has to be in format of [$data=>$ts]
     * @param $perPage
     */
    public function __construct(array $items, $perPage)
    {
        $this->mapping = $items;
        parent::__construct($items, $perPage, null, []);
    }

    public function getMapping()
    {
        return $this->mapping;
    }

    /**
     * Set the items for the paginator.
     *
     * @param  mixed  $items
     * @return void
     */
    protected function setItems($items)
    {
        $items = array_keys($items);

        parent::setItems($items);
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'data' => $this->items->toArray(),
            'from' => $this->fromTime(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path,
            'per_page' => $this->perPage(),
            'to' => $this->toTime(),
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
    public function fromTime()
    {
        return $this->items->count() > 0 ? $this->mapping[$this->items->first()] : null;
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
    public function toTime()
    {
        return $this->items->count() > 0 ? $this->mapping[$this->items->last()] : null;
    }

    /**
     * Get the URL for the next page.
     *
     * @return string|null
     */
    public function nextPageUrl()
    {
        return $this->url($this->toTime());
    }

    /**
     * Contact and append another paginator (b) to the current one (a),
     * Item should persist the order following the scheme of a + b
     * FromTime should taken from a
     * ToTime should taken from b
     *
     * @param TimeSeriesPaginator $p
     * @return $this
     */
    public function concatPaginator(TimeSeriesPaginator $p)
    {
        $this->mapping = array_merge($this->mapping, $p->getMapping());
        $newItems = $this->items->concat($p->items())->toArray();
        $newItems = array_combine($newItems, array_fill(0, count($newItems), null));
        $this->setItems($newItems);
        return $this;
    }

    public function 

}
