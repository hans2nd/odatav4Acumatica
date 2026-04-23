<?php

namespace App\Services;

use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncService
{
    private AcumaticaODataService $odataService;
    private SchemaManager $schemaManager;

    public function __construct(AcumaticaODataService $odataService, SchemaManager $schemaManager)
    {
        $this->odataService  = $odataService;
        $this->schemaManager = $schemaManager;
    }

    /*
    |--------------------------------------------------------------------------
    | Sync a Single Entity
    |--------------------------------------------------------------------------
    */

    /**
     * Sync a single entity from Acumatica OData to the local SQL Server.
     *
     * @param  string  $entityKey  Key from config('acumatica.entities')
     * @return SyncLog
     */
    public function syncEntity(string $entityKey): SyncLog
    {
        $entity = config("acumatica.entities.{$entityKey}");

        if (! $entity) {
            throw new \InvalidArgumentException("Unknown entity key: {$entityKey}");
        }

        $odataEntity = $entity['odata_entity'];
        $tableName   = $entity['table'];

        // Create a log record
        $log = SyncLog::create([
            'entity_key'         => $entityKey,
            'entity_description' => $entity['description'] ?? $entityKey,
            'odata_entity'       => $odataEntity,
            'table_name'         => $tableName,
            'type'               => $entity['type'] ?? 'master',
            'status'             => 'running',
            'started_at'         => now(),
        ]);

        $startTime = microtime(true);

        try {
            // 1. Login to Acumatica when OData is configured for session cookies
            Log::info("Sync [{$entityKey}]: Starting…");
            if ($this->odataService->usesSessionAuth()) {
                $this->odataService->login();
            }

            // --- BEGIN INCREMENTAL LOGIC ---
            $filter = '';
            $isIncremental = false;
            $lastModifiedCol = null;

            if ($this->schemaManager->tableExists($tableName)) {
                $columns = \Illuminate\Support\Facades\Schema::getColumnListing($tableName);
                foreach ($columns as $col) {
                    if (strtolower($col) === 'lastmodifieddatetime') {
                        $lastModifiedCol = $col;
                        break;
                    }
                }

                if ($lastModifiedCol) {
                    $maxModifiedStr = DB::table($tableName)->max($lastModifiedCol);
                    if ($maxModifiedStr) {
                        $timestamp = strtotime($maxModifiedStr);
                        if ($timestamp) {
                            $odataDate = gmdate('Y-m-d\TH:i:s\Z', $timestamp);
                            $filter = "LastModifiedDateTime gt {$odataDate}";
                            $isIncremental = true;
                            Log::info("Sync [{$entityKey}]: Incremental sync starting from {$odataDate}");
                        }
                    }
                }
            }
            // --- END INCREMENTAL LOGIC ---

            // 2. Fetch records via OData
            Log::info("Sync [{$entityKey}]: Fetching data from {$odataEntity}…");
            $records = $this->odataService->fetchAllRecords($odataEntity, $filter);
            $fetchedCount = count($records);
            Log::info("Sync [{$entityKey}]: Fetched {$fetchedCount} records.");

            if ($fetchedCount === 0) {
                $log->update([
                    'status'           => 'success',
                    'records_fetched'  => 0,
                    'records_inserted' => 0,
                    'duration_seconds' => round(microtime(true) - $startTime, 2),
                    'completed_at'     => now(),
                ]);

                if ($this->odataService->usesSessionAuth()) {
                    $this->odataService->logout();
                }
                return $log;
            }

            // 3. Ensure table exists (create from first record if needed)
            $this->schemaManager->ensureTable($tableName, $records[0]);

            // 4. Clean records (remove @odata.* metadata, sanitize column names)
            $cleanData = $this->schemaManager->cleanRecords($records);

            // 5. Upsert existing data or Insert new data in a transaction
            if ($isIncremental) {
                $insertedCount = $this->upsertTableData($tableName, $cleanData);
            } else {
                $insertedCount = $this->replaceTableData($tableName, $cleanData);
            }

            // 6. Update log
            $log->update([
                'status'           => 'success',
                'records_fetched'  => $fetchedCount,
                'records_inserted' => $insertedCount,
                'duration_seconds' => round(microtime(true) - $startTime, 2),
                'completed_at'     => now(),
            ]);

            Log::info("Sync [{$entityKey}]: Completed — {$insertedCount} records inserted in {$log->duration_seconds}s.");

            // 7. Logout
            if ($this->odataService->usesSessionAuth()) {
                $this->odataService->logout();
            }

            return $log;

        } catch (\Throwable $e) {
            Log::error("Sync [{$entityKey}]: Failed.", [
                'message' => $e->getMessage(),
                'trace'   => mb_substr($e->getTraceAsString(), 0, 2000),
            ]);

            $log->update([
                'status'           => 'failed',
                'error_message'    => mb_substr($e->getMessage(), 0, 2000),
                'duration_seconds' => round(microtime(true) - $startTime, 2),
                'completed_at'     => now(),
            ]);

            try {
                if ($this->odataService->usesSessionAuth()) {
                    $this->odataService->logout();
                }
            } catch (\Throwable $ignored) {
            }

            return $log;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Sync All Entities
    |--------------------------------------------------------------------------
    */

    /**
     * Sync all configured entities. Master data first, then transactions.
     *
     * @return array<string, SyncLog>
     */
    public function syncAll(): array
    {
        $entities = config('acumatica.entities', []);
        $results  = [];

        // Sort: master first, then transaction
        $sorted = collect($entities)->sortBy(fn ($e) => $e['type'] === 'master' ? 0 : 1);

        foreach ($sorted as $key => $entity) {
            $results[$key] = $this->syncEntity($key);
        }

        return $results;
    }

    /**
     * Sync only master data entities.
     */
    public function syncMaster(): array
    {
        return $this->syncByType('master');
    }

    /**
     * Sync only transaction entities.
     */
    public function syncTransactions(): array
    {
        return $this->syncByType('transaction');
    }

    private function syncByType(string $type): array
    {
        $entities = config('acumatica.entities', []);
        $results  = [];

        foreach ($entities as $key => $entity) {
            if (($entity['type'] ?? '') === $type) {
                $results[$key] = $this->syncEntity($key);
            }
        }

        return $results;
    }

    /*
    |--------------------------------------------------------------------------
    | Data Insertion
    |--------------------------------------------------------------------------
    */

    /**
     * Replace all data in a table: DELETE existing → INSERT new in a transaction.
     */
    private function replaceTableData(string $tableName, array $cleanData): int
    {
        $inserted = 0;

        DB::transaction(function () use ($tableName, $cleanData, &$inserted) {
            // Delete all existing rows
            DB::table($tableName)->delete();

            // SQL Server supports a maximum of 2100 parameters per statement.
            $columnCount = max(count($cleanData[0] ?? []), 1);
            $chunkSize = max(intdiv(2000, $columnCount), 1);
            $chunks = array_chunk($cleanData, $chunkSize);

            foreach ($chunks as $chunk) {
                DB::table($tableName)->insert($chunk);
                $inserted += count($chunk);
            }
        });

        return $inserted;
    }

    /**
     * Upsert data in a table: UPDATE existing if ID matches → INSERT new in a transaction.
     */
    private function upsertTableData(string $tableName, array $cleanData): int
    {
        $insertedOrUpdated = 0;

        DB::transaction(function () use ($tableName, $cleanData, &$insertedOrUpdated) {
            $hasId = isset($cleanData[0]['id']);
            $hasNoteId = isset($cleanData[0]['NoteID']);

            foreach ($cleanData as $row) {
                $updated = false;

                if ($hasId && !empty($row['id'])) {
                    $exists = DB::table($tableName)->where('id', $row['id'])->exists();
                    if ($exists) {
                        DB::table($tableName)->where('id', $row['id'])->update($row);
                        $updated = true;
                    }
                } elseif ($hasNoteId && !empty($row['NoteID'])) {
                    $exists = DB::table($tableName)->where('NoteID', $row['NoteID'])->exists();
                    if ($exists) {
                        DB::table($tableName)->where('NoteID', $row['NoteID'])->update($row);
                        $updated = true;
                    }
                }

                if (!$updated) {
                    DB::table($tableName)->insert($row);
                }

                $insertedOrUpdated++;
            }
        });

        return $insertedOrUpdated;
    }

    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    */

    /**
     * Get summary statistics for the dashboard.
     */
    public function getDashboardStats(): array
    {
        $entities = config('acumatica.entities', []);
        $totalRecords = 0;
        $entityStats  = [];

        foreach ($entities as $key => $entity) {
            $count = $this->schemaManager->getRowCount($entity['table']);
            $totalRecords += $count;

            $lastSync = SyncLog::where('entity_key', $key)
                ->whereIn('status', ['success', 'failed'])
                ->orderByDesc('id')
                ->first();

            $entityStats[$key] = [
                'description' => $entity['description'] ?? $key,
                'table'       => $entity['table'],
                'type'        => $entity['type'],
                'records'     => $count,
                'last_sync'   => $lastSync,
                'table_exists' => $this->schemaManager->tableExists($entity['table']),
            ];
        }

        $successCount = SyncLog::successful()->count();
        $failedCount  = SyncLog::failed()->count();
        $runningCount = SyncLog::running()->count();

        return [
            'total_records'       => $totalRecords,
            'total_entities'      => count($entities),
            'master_entities'     => collect($entities)->where('type', 'master')->count(),
            'transaction_entities' => collect($entities)->where('type', 'transaction')->count(),
            'running_jobs'        => $runningCount,
            'success_count'       => $successCount,
            'failed_count'        => $failedCount,
            'entity_stats'        => $entityStats,
        ];
    }
}
