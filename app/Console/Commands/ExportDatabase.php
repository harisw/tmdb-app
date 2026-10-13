<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class ExportDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:export';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run PG Dump and upload to S3';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $db = config('database.connections.pgsql');

        Process::timeout(1800)->env([
            'PGHOST' => $db['host'],
            'PGPORT' => $db['port'],
            'PGDATABASE' => $db['database'],
            'PGUSER' => $db['username'],
            'PGPASSWORD' => $db['password'],
            'AWS_BUCKET' => config('filesystems.disks.s3.bucket'),
            'LOG_GROUP' => config('app.export_log_group'),
        ])->run(['bash', base_path('scripts/db-dump.sh')])->throw();
    }
}
