<?php

namespace App\Console\Commands;

use App\Services\SyncService;
use Illuminate\Console\Command;

class SyncAcumaticaData extends Command
{
    protected $signature = 'acumatica:sync
                            {entity? : Entity key to sync (e.g. Customer, SalesOrder). Omit for all.}
                            {--type= : Sync only entities of this type: master or transaction}
                            {--list : List all available entities}';

    protected $description = 'Sync data from Acumatica OData v4 to local SQL Server';

    public function handle(SyncService $syncService): int
    {
        // List entities
        if ($this->option('list')) {
            $this->listEntities();
            return self::SUCCESS;
        }

        $entity = $this->argument('entity');
        $type   = $this->option('type');

        // Sync specific entity
        if ($entity) {
            return $this->syncSingleEntity($syncService, $entity);
        }

        // Sync by type
        if ($type) {
            return $this->syncByType($syncService, $type);
        }

        // Sync all
        return $this->syncAllEntities($syncService);
    }

    private function syncSingleEntity(SyncService $syncService, string $entityKey): int
    {
        $config = config("acumatica.entities.{$entityKey}");

        if (! $config) {
            $this->error("Unknown entity: {$entityKey}");
            $this->line('');
            $this->listEntities();
            return self::FAILURE;
        }

        $this->info("🔄 Syncing [{$entityKey}] — {$config['description']}…");
        $this->newLine();

        $log = $syncService->syncEntity($entityKey);

        if ($log->status === 'success') {
            $this->info("✅ [{$entityKey}] — {$log->records_inserted} records in {$log->duration_seconds}s");
        } else {
            $this->error("❌ [{$entityKey}] — {$log->error_message}");
        }

        return $log->status === 'success' ? self::SUCCESS : self::FAILURE;
    }

    private function syncByType(SyncService $syncService, string $type): int
    {
        $this->info("🔄 Syncing all [{$type}] entities…");
        $this->newLine();

        $results = $type === 'master'
            ? $syncService->syncMaster()
            : $syncService->syncTransactions();

        return $this->displayResults($results);
    }

    private function syncAllEntities(SyncService $syncService): int
    {
        $entities = config('acumatica.entities', []);
        $this->info('🔄 Syncing ALL ' . count($entities) . ' entities…');
        $this->newLine();

        $results = $syncService->syncAll();

        return $this->displayResults($results);
    }

    private function displayResults(array $results): int
    {
        $hasErrors = false;

        $rows = [];
        foreach ($results as $key => $log) {
            $status = $log->status === 'success' ? '✅ Success' : '❌ Failed';
            if ($log->status !== 'success') {
                $hasErrors = true;
            }

            $rows[] = [
                $key,
                $status,
                number_format($log->records_inserted),
                $log->duration_seconds . 's',
                $log->status !== 'success' ? mb_substr($log->error_message ?? '', 0, 60) : '—',
            ];
        }

        $this->table(['Entity', 'Status', 'Records', 'Duration', 'Error'], $rows);

        $totalRecords = collect($results)->sum(fn ($l) => $l->records_inserted);
        $totalTime    = collect($results)->sum(fn ($l) => $l->duration_seconds);

        $this->newLine();
        $this->info("📊 Total: " . number_format($totalRecords) . " records in {$totalTime}s");

        return $hasErrors ? self::FAILURE : self::SUCCESS;
    }

    private function listEntities(): void
    {
        $entities = config('acumatica.entities', []);
        $rows = [];

        foreach ($entities as $key => $e) {
            $rows[] = [$key, $e['type'], $e['odata_entity'], $e['table'], $e['description']];
        }

        $this->table(['Key', 'Type', 'OData Entity', 'SQL Table', 'Description'], $rows);
    }
}
