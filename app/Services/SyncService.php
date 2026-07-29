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
    private \App\Services\Contracts\SyncStateServiceInterface $syncStateService;
    private BottleneckDetector $bottleneckDetector;

    public function __construct(
        AcumaticaODataService $odataService,
        SchemaManager $schemaManager,
        ConnectionService $connectionService,
        \App\Services\Contracts\SyncStateServiceInterface $syncStateService,
        BottleneckDetector $bottleneckDetector
    ) {
        $this->odataService  = $odataService;
        $this->schemaManager = $schemaManager;
        $this->connectionService = $connectionService;
        $this->syncStateService = $syncStateService;
        $this->bottleneckDetector = $bottleneckDetector;
    }

    private function checkForControlSignals(SyncLog $log)
    {
        $signalKey = 'sync_control_log_' . $log->id;
        $signal = \Illuminate\Support\Facades\Cache::get($signalKey);
        
        if ($signal === 'pause') {
            $log->update(['status' => 'paused']);
            while (\Illuminate\Support\Facades\Cache::get($signalKey) === 'pause') {
                $progressKey = "sync_progress_{$log->entity_key}";
                $progress = \Illuminate\Support\Facades\Cache::get($progressKey, []);
                $progress['status'] = 'paused';
                $progress['stage'] = 'Paused by User';
                \Illuminate\Support\Facades\Cache::put($progressKey, $progress, 3600);
                
                sleep(2);
            }
            // If signal changed to stop or cancel while paused, re-evaluate
            $signal = \Illuminate\Support\Facades\Cache::get($signalKey);
            if (!$signal || $signal === 'resume') {
                $log->update(['status' => 'running']);
                \Illuminate\Support\Facades\Cache::forget($signalKey);
            }
        }
        
        if ($signal === 'stop') {
            throw new \App\Exceptions\SyncStopException('Sync stopped gracefully by user.');
        }
        
        if ($signal === 'cancel') {
            throw new \App\Exceptions\SyncCancelException('Sync cancelled forcefully by user.');
        }
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
        $sourceConnKey = !empty($entity['source_connection']) ? $entity['source_connection'] : 'acumatica_default';
        $destConnKey   = !empty($entity['destination_connection']) ? $entity['destination_connection'] : 'database_default';
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
        $schedule = \App\Models\SyncSchedule::firstOrCreate(
            ['entity_key' => $entityKey],
            ['is_active' => true]
        );
        $workerId = uniqid("worker_{$entityKey}_");

        $this->syncStateService->initializeSyncState($schedule->id, $entityKey);
        $this->syncStateService->acquireSynchronizationLock($schedule->id, $workerId);
        $this->syncStateService->startSynchronization($schedule->id);

        try {
            Log::info("Sync [{$entityKey}]: Starting…");
            
            $sourceConnection = $this->connectionService->get($sourceConnKey);
            
            // If the selected connection was deleted or invalid, throw an Exception
            if (!$sourceConnection) {
                throw new \Exception("Koneksi tidak ditemukan! Data koneksi ({$sourceConnKey}) mungkin telah dihapus atau belum dikonfigurasi. Harap periksa menu Connection Management.");
            }
            
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
            $syncStartTime = microtime(true);
            \Illuminate\Support\Facades\Cache::put($progressKey, [
                'current' => 0,
                'total' => null,
                'status' => 'fetching',
                'start_time' => $syncStartTime,
                'stage' => 'Initializing...'
            ], 3600);

            $lastProgressUpdate = $syncStartTime;
            $fetchedCount = 0;
            $insertedCount = 0;
            $hasData = false;
            $totalRecordsCount = null;

            $processChunkCallback = function($recordsChunk) use (&$fetchedCount, &$insertedCount, &$hasData, &$totalRecordsCount, $tableName, $isIncremental, $customFilters, $progressKey, $destConnection, $log, $schedule, $syncStartTime) {
                // Check Execution Control Signals (Pause, Stop, Cancel)
                $this->checkForControlSignals($log);
                
                $chunkStartTime = microtime(true);
                $memoryMb = memory_get_usage(true) / 1024 / 1024;

                $isFirstChunk = !$hasData;
                $hasData = true;
                
                $fetchedCount += count($recordsChunk);
                
                if ($isFirstChunk) {
                    $this->schemaManager->ensureTable($destConnection, $tableName, $recordsChunk[0]);
                }
                
                $cleanData = $this->schemaManager->cleanRecords($recordsChunk);
                
                $fetchTimeMs = (microtime(true) - $chunkStartTime) * 1000;
                $writeStartTime = microtime(true);

                if ($isIncremental) {
                    $inserted = $this->upsertTableData($destConnection, $tableName, $cleanData, $customFilters, $progressKey, $isFirstChunk, $insertedCount, $totalRecordsCount);
                } else {
                    $inserted = $this->replaceTableData($destConnection, $tableName, $cleanData, $customFilters, $progressKey, $isFirstChunk, $insertedCount, $totalRecordsCount);
                }
                
                $writeTimeMs = (microtime(true) - $writeStartTime) * 1000;
                
                $insertedCount += $inserted;
                $this->syncStateService->updateStatistics($schedule->id, count($recordsChunk), $inserted, 0, 0);

                // Throughput calculation
                $elapsed = microtime(true) - $syncStartTime;
                $rowsPerSec = $elapsed > 0 ? $fetchedCount / $elapsed : 0;
                
                // Bottleneck detection
                $this->bottleneckDetector->analyzeChunk($log, $fetchTimeMs, $writeTimeMs, $memoryMb);

                // Update Granular Log Metrics safely
                try {
                    $log->update([
                        'current_stage' => 'processing_chunk',
                        'current_batch' => ceil($fetchedCount / max(1, count($recordsChunk))),
                        'records_updated' => $inserted, // Approximation for now
                        'rows_per_second' => $rowsPerSec,
                        'peak_memory_mb' => max($log->peak_memory_mb ?? 0, $memoryMb),
                    ]);
                } catch (\Exception $e) {
                    // Ignore if columns don't exist yet (migration not run)
                }
            };

            $progressCallback = function($current, $total) use ($progressKey, &$totalRecordsCount, &$lastProgressUpdate, $syncStartTime) {
                if ($total !== null) {
                    $totalRecordsCount = $total;
                }
                
                $now = microtime(true);
                // Buffer progress update: setiap 1.5 detik atau jika selesai
                if (($now - $lastProgressUpdate) >= 1.5 || $current === $total) {
                    \Illuminate\Support\Facades\Cache::put($progressKey, [
                        'current' => $current,
                        'total' => $totalRecordsCount,
                        'status' => 'fetching',
                        'start_time' => $syncStartTime,
                        'stage' => 'Reading Source'
                    ], 3600);
                    $lastProgressUpdate = $now;
                }
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
            } else if (in_array($sourceConnection['type'], \App\Services\Connections\ConnectionProviderFactory::getDatabaseTypes())) {
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
                $this->syncStateService->updateWatermark($schedule->id, null, null);
                $this->syncStateService->completeSynchronization($schedule->id);
                
                $log->update([
                    'status'           => 'success',
                    'records_fetched'  => 0,
                    'records_inserted' => 0,
                    'duration_seconds' => round(microtime(true) - $startTime, 2),
                    'completed_at'     => now(),
                ]);
                return $log;
            }

            $this->syncStateService->updateWatermark($schedule->id, null, null);
            $endTime = microtime(true);
            $duration = $endTime - $startTime;

            $log->update([
                'status'           => 'success',
                'current_stage'    => 'completed',
                'records_fetched'  => $fetchedCount,
                'records_inserted' => $insertedCount,
                'duration_seconds' => $duration,
                'completed_at'     => now(),
            ]);

            $this->syncStateService->completeSynchronization($schedule->id);
            $schedule->update(['last_run_at' => now()]);

            \Illuminate\Support\Facades\Cache::forget("entity_fields_{$entityKey}");

            Log::info("Sync [{$entityKey}]: Completed — {$insertedCount} records inserted in {$log->duration_seconds}s.");
            \Illuminate\Support\Facades\Cache::forget($progressKey);
            return $log;

        } catch (\App\Exceptions\SyncStopException $e) {
            $endTime = microtime(true);
            $log->update([
                'status'           => 'stopped',
                'current_stage'    => 'stopped_by_user',
                'error_message'    => $e->getMessage(),
                'duration_seconds' => $endTime - $startTime,
                'completed_at'     => now(),
            ]);
            $this->syncStateService->failSynchronization($schedule->id);
            Log::info("Sync [{$entityKey}]: Stopped safely.");
            return $log;

        } catch (\App\Exceptions\SyncCancelException $e) {
            $endTime = microtime(true);
            $log->update([
                'status'           => 'cancelled',
                'current_stage'    => 'cancelled_by_user',
                'error_message'    => $e->getMessage(),
                'duration_seconds' => $endTime - $startTime,
                'completed_at'     => now(),
            ]);
            $this->syncStateService->failSynchronization($schedule->id);
            Log::info("Sync [{$entityKey}]: Cancelled forcefully.");
            return $log;

        } catch (\Throwable $e) {
            try {
                $this->syncStateService->failSynchronization($schedule->id);
            } catch (\Exception $stateException) {
                // Ignore state transition errors to prevent masking original exception
            }
            
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
        } finally {
            try {
                if (isset($schedule) && isset($workerId)) {
                    $this->syncStateService->releaseSynchronizationLock($schedule->id, $workerId);
                }
            } catch (\Exception $e) {
                // Ignore lock release failures
            }
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

    private function replaceTableData(?string $destConnection, string $tableName, array $cleanData, array $customFilters = [], ?string $progressKey = null, bool $isFirstChunk = false, int $baseInserted = 0, ?int $totalRecordsCount = null): int
    {
        $inserted = 0;
        $syncStartTime = microtime(true);
        $lastProgressUpdate = $syncStartTime;

        DB::connection($destConnection)->transaction(function () use ($destConnection, $tableName, $cleanData, &$inserted, $customFilters, $progressKey, $isFirstChunk, $baseInserted, $totalRecordsCount, $syncStartTime, &$lastProgressUpdate) {
            if ($isFirstChunk) {
                if (!empty($customFilters)) {
                    $deletedCount = $this->deleteByFilterScope($destConnection, $tableName, $customFilters);
                    Log::info("Sync: Deleted {$deletedCount} existing rows from [{$tableName}] within filter scope before re-insert.");
                } else {
                    DB::connection($destConnection)->table($tableName)->delete();
                }
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
                    $now = microtime(true);
                    if (($now - $lastProgressUpdate) >= 1.5) {
                        \Illuminate\Support\Facades\Cache::put($progressKey, [
                            'current' => $baseInserted + $inserted,
                            'total' => $totalRecordsCount,
                            'status' => 'inserting',
                            'start_time' => $syncStartTime,
                            'stage' => 'Persistence Pipeline'
                        ], 3600);
                        $lastProgressUpdate = $now;
                    }
                }
            }
        }, 5);
        return $inserted;
    }

    private function upsertTableData(?string $destConnection, string $tableName, array $cleanData, array $customFilters = [], ?string $progressKey = null, bool $isFirstChunk = false, int $baseInserted = 0, ?int $totalRecordsCount = null): int
    {
        $insertedOrUpdated = 0;
        $syncStartTime = microtime(true);
        $lastProgressUpdate = $syncStartTime;

        DB::connection($destConnection)->transaction(function () use ($destConnection, $tableName, $cleanData, &$insertedOrUpdated, $customFilters, $progressKey, $isFirstChunk, $baseInserted, $totalRecordsCount, $syncStartTime, &$lastProgressUpdate) {
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
                        $now = microtime(true);
                        if (($now - $lastProgressUpdate) >= 1.5) {
                            \Illuminate\Support\Facades\Cache::put($progressKey, [
                                'current' => $baseInserted + $insertedOrUpdated,
                                'total' => $totalRecordsCount,
                                'status' => 'inserting',
                                'start_time' => $syncStartTime,
                                'stage' => 'Persistence Pipeline'
                            ], 3600);
                            $lastProgressUpdate = $now;
                        }
                    }
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
