<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetupDatabase extends Command
{
    protected $signature = 'acumatica:setup';

    protected $description = 'Create the AcumaticaSync database in SQL Server if it does not exist, then run migrations.';

    public function handle(): int
    {
        $database = config('database.connections.sqlsrv.database', 'AcumaticaSync');
        $host     = config('database.connections.sqlsrv.host', 'localhost');

        $this->info("Setting up database [{$database}] on [{$host}]…");

        try {
            // Connect to 'master' to create the database
            config(['database.connections.sqlsrv_master' => array_merge(
                config('database.connections.sqlsrv'),
                ['database' => 'master']
            )]);

            $exists = DB::connection('sqlsrv_master')
                ->select("SELECT DB_ID(?) AS db_id", [$database]);

            if (empty($exists) || is_null($exists[0]->db_id)) {
                $this->info("Creating database [{$database}]…");
                DB::connection('sqlsrv_master')
                    ->statement("CREATE DATABASE [{$database}]");
                $this->info("✅ Database [{$database}] created.");
            } else {
                $this->info("Database [{$database}] already exists.");
            }

            // Purge connection cache so migrations use the correct DB
            DB::purge('sqlsrv_master');

        } catch (\Exception $e) {
            $this->error('Failed to create database: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Please create the database manually:');
            $this->line("  CREATE DATABASE [{$database}];");
            $this->newLine();
        }

        // Run migrations
        $this->info('Running migrations…');
        $this->call('migrate', ['--force' => true]);

        $this->newLine();
        $this->info('🎉 Setup complete! You can now run: php artisan acumatica:sync');

        return self::SUCCESS;
    }
}
