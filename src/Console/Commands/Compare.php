<?php

namespace Essam\CompareDB\Console\Commands;

use Essam\CompareDB\Facades\CompareDB;
use Illuminate\Console\Command;

class Compare extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compare:db';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compare the database tables and columns between two databases , and add the missing tables and columns to the destination database.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $compare = CompareDB::compare();
        $compare
            ? $this->info('Compare Done Successfully And sql files created in the storage/app/compareDB folder')
            : $this->error('Compare Failed');
        return Command::SUCCESS;
    }
}
