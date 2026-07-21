<?php

namespace App\Services;

use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncService
{
    private AcumaticaODataService $odataService;
    private SchemaManager $schemaManager;
    private ConnectionService $connectionService;

    public function __construct(AcumaticaODataService $odataService, SchemaManager $schemaManager, ConnectionService $connectionService)
    {
        $this->odataService  = $odataService;
        $this->schemaManager = $schemaManager;
        $this->connectionService = $connectionService;
    }

    /*
    |--------------------------------------------------------------------------
    | Sync a Single Entity
    |--------------------------------------------------------------------------
    */

    public function syncEntity(string $entityKey, array $customFilters = [], string $source = 'manual'): SyncLog
    {
        $entity = config("acumatica.entities.{$entityKey}");

        if (! $entity) {
            throw new \InvalidArgumentException("Unknown entity key: {$entityKey}");
        }

        $odataEntity = $entity['odata_entity'];
        $tableName   = $entity['table'];
        $sourceConnKey = $entity['source_connection'] ?? 'acumatica_default';
        $destConnKey   = $entity['destination_connection'] ?? 'database_default';

        $sourceConnection = $this->connectionService->get($sourceConnKey);
        
        // Ensure default connections fallback if missing
        if (!$sourceConnection && $sourceConnKey === 'acumatica_default') {
            $sourceConnection = ['type' => 'odata'];
        }

        $destConnection = $destConnKey; // Use explicitly so it uses the dynamic connection

        // Create a log record
        $log = SyncLog::create([
            'entity_key'         => $entityKey,
            'entity_description' => $entity['description'] ?? $entityKey,
            'odata_entity'       => $odataEntity,
            'table_name'         => $tableName,
            'type'               => $entity['type'] ?? 'master',
            'source'             => $source,
            'status'             => 'running',
            'started_at'         => now(),
        ]);

        $startTime = microtime(true);

        try {
            Log::info("Sync [{$entityKey}]: Starting…");
            
            // --- BEGIN INCREMENTAL LOGIC ---
            $filter = '';
            $isIncremental = false;
            $lastModifiedCol = null;

            if ($this->schemaManager->tableExists($destConnection, $tableName)) {
                $columns = \Illuminate\Support\Facades\Schema::connection($destConnection)->getColumnListing($tableName);
                foreach ($columns as $col) {
                    if (strtolower($col) === 'lastmodifieddatetime') {
                        $lastModifiedCol = $col;
                        break;
                    }
                }

                if ($lastModifiedCol) {
                    $maxModifiedStr = DB::connection($destConnection)->table($tableName)->max($lastModifiedCol);
                    if ($maxModifiedStr) {
                        try {
                            $isV4 = config('acumatica.odata_path', 'odatav4') === 'odatav4';
                            $dateStr = \Carbon\Carbon::parse($maxModifiedStr)->setTimezone('UTC')->format('Y-m-d\TH:i:s.v');
                            $odataDate = $isV4 ? "{$dateStr}Z" : "datetime'{$dateStr}'";
                            
                            $filter = "LastModifiedDateTime gt {$odataDate}";
                            $isIncremental = true;
                            Log::info("Sync [{$entityKey}]: Incremental sync starting from {$odataDate}");
                        } catch (\Exception $e) {
                            // Ignore if unparseable
                        }
                    }
                }
            }
            // --- END INCREMENTAL LOGIC ---

            // --- BEGIN CUSTOM FILTERS ---
            $customFilterStrings = [];
            foreach ($customFilters as $cf) {
                $field = $cf['field'] ?? '';
                $op    = $cf['operator'] ?? 'eq';
                $val   = $cf['value'] ?? '';
                
                if ($field && $val !== '') {
                    $valSafe = str_replace("'", "''", $val);
                    $isDateField = stripos($field, 'date') !== false || stripos($field, 'time') !== false;
                    $parsedDateStr = null;
                    
                    if ($isDateField) {
                        try {
                            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $valSafe, $m)) {
                                $date = \Carbon\Carbon::create($m[3], $m[2], $m[1], 0, 0, 0);
                            } else {
                                $date = \Carbon\Carbon::parse($valSafe);
                            }
                            $isV4 = config('acumatica.odata_path', 'odatav4') === 'odatav4';
                            $dateStr = $date->setTimezone('UTC')->format('Y-m-d\TH:i:s.v');
                            $parsedDateStr = $isV4 ? "{$dateStr}Z" : "datetime'{$dateStr}'";
                        } catch (\Exception $e) {
                            $parsedDateStr = null;
                        }
                    }
                    
                    if ($op === 'contains') {
                        $customFilterStrings[] = "contains({$field}, '{$valSafe}')";
                    } elseif ($op === 'startswith') {
                        $customFilterStrings[] = "startswith({$field}, '{$valSafe}')";
                    } elseif ($op === 'endswith') {
                        $customFilterStrings[] = "endswith({$field}, '{$valSafe}')";
                    } else {
                        if ($parsedDateStr) {
                            $customFilterStrings[] = "{$field} {$op} {$parsedDateStr}";
                        } elseif (is_numeric($valSafe) || in_array(strtolower($valSafe), ['true', 'false'])) {
                            $customFilterStrings[] = "{$field} {$op} {$valSafe}";
                        } else {
                            $customFilterStrings[] = "{$field} {$op} '{$valSafe}'";
                        }
                    }
                }
            }

            $finalFilters = [];
            if (!empty($customFilterStrings)) {
                $customCombined = implode(' and ', $customFilterStrings);
                $finalFilters[] = "({$customCombined})";
                $isIncremental = true;
                Log::info("Sync [{$entityKey}]: Custom filters applied -> {$customCombined}. Auto-incremental filter is disabled to allow fetching historical data.");
            } else if ($filter) {
                $finalFilters[] = "({$filter})";
            }

            $finalFilterString = implode(' and ', $finalFilters);
            // --- END CUSTOM FILTERS ---

            $progressKey = "sync_progress_{$entityKey}";
            \Illuminate\Support\Facades\Cache::put($progressKey, [
                'current' => 0,
                'total' => null,
                'status' => 'fetching'
            ], 3600);

            $fetchedCount = 0;
            $insertedCount = 0;
            $hasData = false;
            $totalRecordsCount = null;

            $processChunkCallback = function($recordsChunk) use (&$fetchedCount, &$insertedCount, &$hasData, &$totalRecordsCount, $tableName, $isIncremental, $customFilters, $progressKey, $destConnection, $log) {
                if (\Illuminate\Support\Facades\Cache::get('sync_abort_log_' . $log->id)) {
                    throw new \Exception('Sync aborted manually by user.');
                }

                $isFirstChunk = !$hasData;
                $hasData = true;
                
                $fetchedCount += count($recordsChunk);
                
                if ($isFirstChunk) {
                    $this->schemaManager->ensureTable($destConnection, $tableName, $recordsChunk[0]);
                }
                Log::debug("schemaManager class: " . get_class($this->schemaManager));
                $cleanData = $this->schemaManager->cleanRecords($recordsChunk);

                if ($isIncremental) {
                    $inserted = $this->upsertTableData($destConnection, $tableName, $cleanData, $customFilters, $progressKey, $isFirstChunk, $insertedCount, $totalRecordsCount);
                } else {
                    $inserted = $this->replaceTableData($destConnection, $tableName, $cleanData, $progressKey, $isFirstChunk, $insertedCount, $totalRecordsCount);
                }
                
                $insertedCount += $inserted;
            };

            $progressCallback = function($current, $total) use ($progressKey, &$totalRecordsCount) {
                if ($total !== null) {
                    $totalRecordsCount = $total;
                }
                \Illuminate\Support\Facades\Cache::put($progressKey, [
                    'current' => $current,
                    'total' => $totalRecordsCount,
                    'status' => 'fetching'
                ], 3600);
            };

            // FETCH DATA BASED ON SOURCE CONNECTION TYPE
            if (($sourceConnection['type'] ?? 'odata') === 'odata') {
                $this->odataService->setConnectionConfig($sourceConnection);

                if ($this->odataService->usesSessionAuth()) {
                    $this->odataService->login();
                }
                
                $this->odataService->fetchAllRecords($odataEntity, $finalFilterString, $progressCallback, $processChunkCallback);
                
                if ($this->odataService->usesSessionAuth()) {
                    $this->odataService->logout();
                }
                $this->odataService->resetPersistentHttp();
            } else if (in_array($sourceConnection['type'], ['mysql', 'sqlsrv'])) {
                // Relational DB Source
                // Using Laravel's DB chunking
                $query = DB::connection($sourceConnKey)->table($odataEntity);
                
                // Apply custom filters for DB sources
                if (!empty($customFilters)) {
                    $query->where(function ($q) use ($customFilters) {
                        foreach ($customFilters as $cf) {
                            $field = $cf['field'] ?? '';
                            $op    = $cf['operator'] ?? 'eq';
                            $val   = $cf['value'] ?? '';
                            
                            if ($field && $val !== '') {
                                switch ($op) {
                                    case 'eq': $q->where($field, '=', $val); break;
                                    case 'ne': $q->where($field, '!=', $val); break;
                                    case 'contains': $q->where($field, 'like', '%' . $val . '%'); break;
                                    case 'startswith': $q->where($field, 'like', $val . '%'); break;
                                    case 'endswith': $q->where($field, 'like', '%' . $val); break;
                                    case 'gt': $q->where($field, '>', $val); break;
                                    case 'ge': $q->where($field, '>=', $val); break;
                                    case 'lt': $q->where($field, '<', $val); break;
                                    case 'le': $q->where($field, '<=', $val); break;
                                }
                            }
                        }
                    });
                }
                
                $totalRecordsCount = $query->count();
                $progressCallback(0, $totalRecordsCount);
                
                $chunkSize = 1000;
                // If there's no PK, chunk may fail, we'll use chunkById if 'id' exists, else basic get() chunks if possible
                try {
                    $query->chunkById($chunkSize, function($records) use ($processChunkCallback, &$fetchedCount, $progressCallback, $totalRecordsCount) {
                        $arr = $records->map(fn($record) => (array) $record)->toArray();
                        $processChunkCallback($arr);
                        $progressCallback($fetchedCount, $totalRecordsCount);
                    });
                } catch (\Exception $e) {
                    // Fallback if no 'id' column. chunk() requires an orderBy clause
                    $firstRow = DB::connection($sourceConnKey)->table($odataEntity)->first();
                    $firstCol = $firstRow ? array_key_first((array)$firstRow) : null;
                    if ($firstCol) {
                        $query->orderBy($firstCol);
                    }
                    
                    $query->chunk($chunkSize, function($records) use ($processChunkCallback, &$fetchedCount, $progressCallback, $totalRecordsCount) {
                        $arr = $records->map(fn($record) => (array) $record)->toArray();
                        $processChunkCallback($arr);
                        $progressCallback($fetchedCount, $totalRecordsCount);
                    });
                }
            }

            if ($fetchedCount === 0) {
                $log->update([
                    'status'           => 'success',
                    'records_fetched'  => 0,
                    'records_inserted' => 0,
                    'duration_seconds' => round(microtime(true) - $startTime, 2),
                    'completed_at'     => now(),
                ]);
                return $log;
            }

            $log->update([
                'status'           => 'success',
                'records_fetched'  => $fetchedCount,
                'records_inserted' => $insertedCount,
                'duration_seconds' => round(microtime(true) - $startTime, 2),
                'completed_at'     => now(),
            ]);

            \Illuminate\Support\Facades\Cache::forget("entity_fields_{$entityKey}");

            Log::info("Sync [{$entityKey}]: Completed — {$insertedCount} records inserted in {$log->duration_seconds}s.");
            \Illuminate\Support\Facades\Cache::forget($progressKey);
            return $log;

        } catch (\Throwable $e) {
            Log::error("Sync [{$entityKey}]: Failed.", [
                'message' => $e->getMessage(),
                'trace'   => mb_substr($e->getTraceAsString(), 0, 2000),
            ]);

            $log->update([
                'status'           => 'failed',
                'error_message'    => mb_substr($e->getMessage(), 0, 15000),
                'duration_seconds' => round(microtime(true) - $startTime, 2),
                'completed_at'     => now(),
            ]);

            if (isset($progressKey)) {
                \Illuminate\Support\Facades\Cache::forget($progressKey);
            }

            return $log;
        }
    }

    public function syncAll(): array
    {
        $entities = config('acumatica.entities', []);
        $results  = [];
        $sorted = collect($entities)->sortBy(fn ($e) => ($e['type'] ?? '') === 'master' ? 0 : 1);
        foreach ($sorted as $key => $entity) {
            $results[$key] = $this->syncEntity($key);
        }
        return $results;
    }

    private function replaceTableData(?string $destConnection, string $tableName, array $cleanData, ?string $progressKey = null, bool $isFirstChunk = false, int $baseInserted = 0, ?int $totalRecordsCount = null): int
    {
        $inserted = 0;
        DB::connection($destConnection)->transaction(function () use ($destConnection, $tableName, $cleanData, &$inserted, $progressKey, $isFirstChunk, $baseInserted, $totalRecordsCount) {
            if ($isFirstChunk) {
                DB::connection($destConnection)->table($tableName)->delete();
            }

            $driver = DB::connection($destConnection)->getDriverName();
            $maxParams = $driver === 'sqlsrv' ? 2000 : 60000;
            $columnCount = max(count($cleanData[0] ?? []), 1);
            $chunkSize = max(intdiv($maxParams, $columnCount), 1);
            $chunks = array_chunk($cleanData, $chunkSize);

            foreach ($chunks as $chunk) {
                if (isset($chunk[0]['tstamp'])) {
                    Log::debug('replaceTableData tstamp dump:', ['value' => $chunk[0]['tstamp'], 'type' => gettype($chunk[0]['tstamp'])]);
                }
                DB::connection($destConnection)->table($tableName)->insert($chunk);
                $inserted += count($chunk);
                
                if ($progressKey) {
                    \Illuminate\Support\Facades\Cache::put($progressKey, [
                        'current' => $baseInserted + $inserted,
                        'total' => $totalRecordsCount,
                        'status' => 'inserting'
                    ], 3600);
                }
            }
        }, 5);
        return $inserted;
    }

    private function upsertTableData(?string $destConnection, string $tableName, array $cleanData, array $customFilters = [], ?string $progressKey = null, bool $isFirstChunk = false, int $baseInserted = 0, ?int $totalRecordsCount = null): int
    {
        $insertedOrUpdated = 0;

        DB::connection($destConnection)->transaction(function () use ($destConnection, $tableName, $cleanData, &$insertedOrUpdated, $customFilters, $progressKey, $isFirstChunk, $baseInserted, $totalRecordsCount) {
            $hasId = isset($cleanData[0]['id']);

            if ($hasId) {
                $uniqueKey = 'id';
                $columnsToUpdate = array_keys($cleanData[0]);
                $columnsToUpdate = array_values(array_diff($columnsToUpdate, [$uniqueKey, '_sync_id']));

                $driver = DB::connection($destConnection)->getDriverName();
                $maxParams = $driver === 'sqlsrv' ? 2000 : 60000;
                $columnCount = max(count($cleanData[0] ?? []), 1);
                $chunkSize = max(intdiv($maxParams, $columnCount), 1);
                
                $chunks = array_chunk($cleanData, $chunkSize);

                foreach ($chunks as $chunk) {
                    if (isset($chunk[0]['tstamp'])) {
                        Log::debug('upsertTableData tstamp dump:', ['value' => $chunk[0]['tstamp'], 'type' => gettype($chunk[0]['tstamp'])]);
                    }
                    DB::connection($destConnection)->table($tableName)->upsert($chunk, [$uniqueKey], $columnsToUpdate);
                    $insertedOrUpdated += count($chunk);
                    
                    if ($progressKey) {
                        \Illuminate\Support\Facades\Cache::put($progressKey, [
                            'current' => $baseInserted + $insertedOrUpdated,
                            'total' => $totalRecordsCount,
                            'status' => 'inserting'
                        ], 3600);
                    }
                }
            } else {
                if (!empty($customFilters)) {
                    if ($isFirstChunk) {
                        $deletedCount = $this->deleteByFilterScope($destConnection, $tableName, $customFilters);
                        Log::info("Sync: Deleted {$deletedCount} existing rows from [{$tableName}] within filter scope before re-insert.");
                    }
                } else {
                    $noteIds = [];
                    if (isset($cleanData[0]['NoteID'])) {
                        $noteIds = array_filter(array_column($cleanData, 'NoteID'));
                        $noteIdCol = 'NoteID';
                    } elseif (isset($cleanData[0]['noteid'])) {
                        $noteIds = array_filter(array_column($cleanData, 'noteid'));
                        $noteIdCol = 'noteid';
                    }

                    if (!empty($noteIds)) {
                        foreach (array_chunk(array_unique($noteIds), 500) as $chunk) {
                            DB::connection($destConnection)->table($tableName)->whereIn($noteIdCol, $chunk)->delete();
                        }
                    } else {
                        $hashes = array_filter(array_column($cleanData, '_row_hash'));
                        if (!empty($hashes)) {
                            foreach (array_chunk($hashes, 500) as $hashChunk) {
                                DB::connection($destConnection)->table($tableName)->whereIn('_row_hash', $hashChunk)->delete();
                            }
                        }
                    }
                }

                $insertedOrUpdated = $this->bulkInsertData($destConnection, $tableName, $cleanData, $progressKey, $baseInserted);
            }
        }, 5);

        return $insertedOrUpdated;
    }

    private function deleteByFilterScope(?string $destConnection, string $tableName, array $customFilters): int
    {
        $query = DB::connection($destConnection)->table($tableName);
        $hasCondition = false;
        $operatorMap = ['eq' => '=', 'ne' => '!=', 'gt' => '>', 'lt' => '<', 'ge' => '>=', 'le' => '<='];

        foreach ($customFilters as $cf) {
            $field = $cf['field'] ?? '';
            $op    = $cf['operator'] ?? 'eq';
            $val   = $cf['value'] ?? '';

            if (!$field || $val === '') continue;

            $colName = $this->schemaManager->sanitizeColumnName($field);

            if ($op === 'contains') {
                $query->where($colName, 'LIKE', '%' . $val . '%');
            } elseif ($op === 'startswith') {
                $query->where($colName, 'LIKE', $val . '%');
            } elseif ($op === 'endswith') {
                $query->where($colName, 'LIKE', '%' . $val);
            } else {
                $sqlOp = $operatorMap[$op] ?? '=';
                $query->where($colName, $sqlOp, $val);
            }

            $hasCondition = true;
        }

        if (!$hasCondition) return 0;
        return $query->delete();
    }

    private function bulkInsertData(?string $destConnection, string $tableName, array $cleanData, ?string $progressKey = null, int $baseInserted = 0): int
    {
        $inserted = 0;
        $driver = DB::connection($destConnection)->getDriverName();
        $maxParams = $driver === 'sqlsrv' ? 2000 : 60000;
        $columnCount = max(count($cleanData[0] ?? []), 1);
        $chunkSize = max(intdiv($maxParams, $columnCount), 1);
        $chunks = array_chunk($cleanData, $chunkSize);

        foreach ($chunks as $chunk) {
            DB::connection($destConnection)->table($tableName)->insert($chunk);
            $inserted += count($chunk);
            if ($progressKey) {
                \Illuminate\Support\Facades\Cache::put($progressKey, [
                    'current' => $baseInserted + $inserted,
                    'total' => null,
                    'status' => 'inserting'
                ], 3600);
            }
        }
        return $inserted;
    }

    public function getDashboardStats(): array
    {
        $entities = config('acumatica.entities', []);
        $totalRecords = 0;
        $entityStats  = [];
        $hasLogsTable = \Illuminate\Support\Facades\Schema::hasTable('sync_logs');

        foreach ($entities as $key => $entity) {
            $destConnKey = $entity['destination_connection'] ?? 'database_default';
            $destConnection = $destConnKey;

            $count = \Illuminate\Support\Facades\Cache::remember(
                "row_count_{$destConnection}_{$entity['table']}",
                60,
                fn () => $this->schemaManager->getRowCount($destConnection, $entity['table'])
            );
            $totalRecords += $count;

            $lastSync = null;
            if ($hasLogsTable) {
                $lastSync = SyncLog::where('entity_key', $key)
                    ->whereIn('status', ['success', 'failed'])
                    ->orderByDesc('id')
                    ->first();
            }

            $entityStats[$key] = [
                'description' => $entity['description'] ?? $key,
                'table'       => $entity['table'],
                'type'        => $entity['type'],
                'records'     => $count,
                'last_sync'   => $lastSync,
                'table_exists' => $this->schemaManager->tableExists($destConnection, $entity['table']),
            ];
        }

        $successCount = $hasLogsTable ? SyncLog::successful()->count() : 0;
        $failedCount  = $hasLogsTable ? SyncLog::failed()->count() : 0;
        $runningCount = $hasLogsTable ? SyncLog::running()->count() : 0;

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
