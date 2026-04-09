<?php

namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;

class MoviesRefreshSearchable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'movies:refresh-searchable';

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

        if (DB::transactionLevel() > 0) {
            DB::statement('REFRESH MATERIALIZED VIEW searchable_movies');
        } else {
            DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY searchable_movies');
        }

        $this->info('*** DONE ***');
    }
}
