<?php

namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;

class TitlesRefreshSearchable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'titles:refresh-searchable';

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
        $this->info('Starting Refresh Searchable Title');

        if (DB::transactionLevel() > 0) {
            DB::statement('REFRESH MATERIALIZED VIEW searchable_titles');
        } else {
            DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY searchable_titles');
        }

        $this->info('*** DONE ***');
    }
}
