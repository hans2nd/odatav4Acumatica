<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AcumaticaResetTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'acumatica:reset-tables';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop all synced Acumatica tables to force schema rebuild';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $entities = config('acumatica.entities', []);
        
        if (empty($entities)) {
            $this->warn('No entities found in configuration.');
            return;
        }

        if (!$this->confirm('This will DROP all local tables synced from Acumatica. Are you sure you want to proceed?')) {
            $this->info('Aborted.');
            return;
        }

        foreach ($entities as $key => $entity) {
            $tableName = $entity['table'] ?? null;
            if ($tableName && \Illuminate\Support\Facades\Schema::hasTable($tableName)) {
                \Illuminate\Support\Facades\Schema::drop($tableName);
                $this->info("Dropped table: {$tableName}");
            }
        }
        
        $this->info('All Acumatica tables have been dropped. Next sync will recreate them with the new schema (including correct indexes).');
    }
}
