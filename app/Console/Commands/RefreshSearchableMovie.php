<?php

namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;

class RefreshSearchableMovie extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:refresh-searchable-movie';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Starting Refresh Searchable Movie');
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY searchable_movies');
        $this->info('*** DONE ***');
    }
}
