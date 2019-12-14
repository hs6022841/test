<?php

namespace App\Console\Commands;

use App\Lib\Feed\FeedContract;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PersistBuffer extends Command
{
    protected $feedService;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'buffer:persist';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Persist feed stored in cache to db after x sec TTL (defined as FEED_PERSIST_TIMEOUT)';

    /**
     * Create a new command instance.
     *
     * @param FeedContract $feedContract
     */
    public function __construct(FeedContract $feedContract)
    {
        $this->feedService = $feedContract;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->feedService->persist();
    }
}
