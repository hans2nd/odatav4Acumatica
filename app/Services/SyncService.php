<?php

namespace App\Services;

use App\Models\SyncLog;
use App\Services\EntityConfigService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncService
{
    private AcumaticaODataService $odataService;
    private SchemaManager $schemaManager;
    private ConnectionService $connectionService;
    private \App\Services\Contracts\SyncStateServiceInterface $syncStateService;
    private BottleneckDetector $bottleneckDetector;
    private \App\Services\Sync\BatchStrategies\BatchStrategyFactory $batchStrategyFactory;
    private EntityConfigService $entityConfigService;

    public function __construct(
        AcumaticaODataService $odataService,
        SchemaManager $schemaManager,
        ConnectionService $connectionService,
        \App\Services\Contracts\SyncStateServiceInterface $syncStateService,
        BottleneckDetector $bottleneckDetector,
        \App\Services\Sync\BatchStrategies\BatchStrategyFactory $batchStrategyFactory,
        EntityConfigService $entityConfigService
    ) {
        $this->odataService = $odataService;
        $this->schemaManager = $schemaManager;
        $this->connectionService = $connectionService;
        $this->syncStateService = $syncStateService;
        $this->bottleneckDetector = $bottleneckDetector;
        $this->batchStrategyFactory = $batchStrategyFactory;
        $this->entityConfigService = $entityConfigService;
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

    public function syncEntity(string $entityKey, array $customFilters = [], string $source = 'manual', ?string $syncJobId = null, ?string $workerId = null, ?string $syncModeOverride = null, ?string $watermarkTypeOverride = null, ?string $watermarkColumnOverride = null): SyncLog
    {
        $entity = $this->entityConfigService->get($entityKey);

        if (!$entity) {
            throw new \InvalidArgumentException("Unknown entity key: {$entityKey}");
        }

        $odataEntity = $entity['odata_entity'];
        $tableName = $entity['table'];
        $sourceConnKey = !empty($entity['source_connection']) ? $entity['source_connection'] : 'acumatica_default';
        $destConnKey = !empty($entity['destination_connection']) ? $entity['destination_connection'] : 'database_default';

        Log::info("Sync [{$entityKey}]: Resolving destination connection for key '{$destConnKey}'");
        $destConnection = $this->connectionService->resolveConnectionName($destConnKey);
        Log::info("Sync [{$entityKey}]: Resolved destination connection to '{$destConnection}'");

        // Disable query log to prevent memory leaks during long-running batch synchronization
        DB::disableQueryLog();
        if ($destConnection) {
            try {
                DB::connection($destConnection)->disableQueryLog();
            } catch (\Throwable $e) {
                // Ignore connection-specific logging errors
            }
        }

        // Remove execution time limit so large entities are not killed mid-execution
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $workerId = $workerId ?? uniqid("worker_{$entityKey}_");

        // Create a log record
        $startTime = microtime(true);
        $schedule = \App\Models\SyncSchedule::firstOrCreate(
            ['entity_key' => $entityKey],
            ['is_active' => true]
        );

        $this->syncStateService->initializeSyncState($schedule->id, $entityKey);
        $this->syncStateService->acquireSynchronizationLock($schedule->id, $workerId);

        try {
            $this->syncStateService->startSynchronization($schedule->id);

            // Create log only AFTER successfully acquiring the lock and starting synchronization
            $log = \App\Models\SyncLog::create([
                'session_id' => $workerId,
                'entity_key' => $entityKey,
                'entity_description' => $entity['description'] ?? $entityKey,
                'odata_entity' => $odataEntity,
                'table_name' => $tableName,
                'type' => $entity['type'] ?? 'master',
                'source' => $source,
                'status' => 'running',
                'started_at' => now(),
            ]);

            Log::info("Sync [{$entityKey}]: Starting…");

            $sourceConnection = $this->connectionService->get($sourceConnKey);

            // If the selected connection was deleted or invalid, throw an Exception
            if (!$sourceConnection) {
                throw new \Exception("Koneksi tidak ditemukan! Data koneksi ({$sourceConnKey}) mungkin telah dihapus atau belum dikonfigurasi. Harap periksa menu Connection Management.");
            }

            // --- BEGIN INCREMENTAL LOGIC ---
            $filter = '';
            $syncMode = $entity['sync_mode'] ?? 'full';
            
            if ($syncModeOverride === 'full') {
                $syncMode = 'full';
            } elseif ($syncModeOverride === 'incremental') {
                $syncMode = 'incremental';
            } elseif ($syncModeOverride === 'backdate') {
                $syncMode = 'backdate';
            }
            
            $isIncremental = in_array($syncMode, ['incremental', 'backdate']);
            $isAppendOnly = $syncMode === 'backdate';
            
            $watermark = null;

            if ($isIncremental && $this->schemaManager->tableExists($destConnection, $tableName)) {
                $lastModifiedCol = $watermarkColumnOverride ?: ($entity['watermark_column'] ?? null);
                $watermarkType = $watermarkTypeOverride ?: ($entity['watermark_type'] ?? 'datetime');
                
                // Fallback to auto-detect if no specific watermark column was configured
                if (empty($lastModifiedCol)) {
                    $columns = \Illuminate\Support\Facades\Schema::connection($destConnection)->getColumnListing($tableName);
                    foreach ($columns as $col) {
                        if (strtolower($col) === 'lastmodifieddatetime') {
                            $lastModifiedCol = $col;
                            break;
                        }
                    }
                }

                if ($lastModifiedCol && !\Illuminate\Support\Facades\Schema::connection($destConnection)->hasColumn($tableName, $lastModifiedCol)) {
                    Log::warning("Sync [{$entityKey}] Session [{$workerId}]: Watermark column '{$lastModifiedCol}' not found in table '{$tableName}'. Proceeding without watermark filter.");
                    $lastModifiedCol = null;
                }

                if ($lastModifiedCol) {
                    $maxModifiedStr = DB::connection($destConnection)->table($tableName)->max($lastModifiedCol);
                    if ($maxModifiedStr !== null) {
                        if ($watermarkType === 'integer') {
                            $watermark = new \App\Services\Sync\Watermarks\IntegerWatermark($lastModifiedCol, $maxModifiedStr);
                        } elseif ($watermarkType === 'string') {
                            $watermark = new \App\Services\Sync\Watermarks\StringWatermark($lastModifiedCol, $maxModifiedStr);
                        } else {
                            $watermark = new \App\Services\Sync\Watermarks\TimestampWatermark($lastModifiedCol, $maxModifiedStr);
                        }
                        
                        $filter = $watermark->toFilterString();
                        Log::info("Sync [{$entityKey}] Session [{$workerId}]: Incremental sync starting from {$filter}");
                    } else {
                        Log::info("Sync [{$entityKey}] Session [{$workerId}]: Incremental sync starting from beginning (table empty)");
                    }
                }
            }
            // --- END INCREMENTAL LOGIC ---

            // --- BEGIN CUSTOM FILTERS ---
            $customFilterStrings = [];
            foreach ($customFilters as $cf) {
                $field = $cf['field'] ?? '';
                $op = $cf['operator'] ?? 'eq';
                $val = $cf['value'] ?? '';

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
                            $dateStr = $date->format('Y-m-d\TH:i:s.v');
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
            if ($filter) {
                $finalFilters[] = "({$filter})";
            }
            if (!empty($customFilterStrings)) {
                $customCombined = implode(' and ', $customFilterStrings);
                $finalFilters[] = "({$customCombined})";
                Log::info("Sync [{$entityKey}] Session [{$workerId}]: Custom filters applied -> {$customCombined}.");
            }

            $finalFilterString = implode(' and ', $finalFilters);
            // --- END CUSTOM FILTERS ---

            $syncStartTime = microtime(true);
            if ($syncJobId) {
                \App\Models\SyncJob::where('id', $syncJobId)->update([
                    'status' => \App\Models\SyncJob::STATUS_RUNNING,
                    'started_at' => now(),
                ]);
            }

            $lastProgressUpdate = $syncStartTime;
            $fetchedCount = 0;
            $insertedCount = 0;
            $hasData = false;
            $totalRecordsCount = null;

            $initialRowCount = 0;
            if ($this->schemaManager->tableExists($destConnection, $tableName)) {
                $initialRowCount = $this->schemaManager->getRowCount($destConnection, $tableName);
            }

            $isFullSync = !$isIncremental;
            $stagingTableName = $tableName . '_staging';
            $writeTableName = $isFullSync ? $stagingTableName : $tableName;

            if ($isFullSync) {
                // Drop any leftover staging table
                \Illuminate\Support\Facades\Schema::connection($destConnection)->dropIfExists($stagingTableName);
            }

            $processChunkCallback = function ($recordsChunk) use (&$fetchedCount, &$insertedCount, &$hasData, &$totalRecordsCount, $tableName, $isIncremental, $isAppendOnly, $customFilters, $syncJobId, $destConnection, $log, $schedule, $syncStartTime, $entityKey, $workerId, $isFullSync, $stagingTableName, $writeTableName, $entity) {
                // Heartbeat to extend the distributed lock
                $this->syncStateService->heartbeatSynchronizationLock($schedule->id, $workerId);

                // Extend PHP execution timer on each active batch so ongoing syncs never get killed by timeout
                @set_time_limit(600);

                // Check Execution Control Signals (Pause, Stop, Cancel)
                $this->checkForControlSignals($log);

                $chunkSize = count($recordsChunk);
                if ($chunkSize === 0) {
                    Log::warning("Sync [{$entityKey}]: Fetched 0 rows in chunk, stopping.");
                    return;
                }
                Log::info("STEP 1: Rows fetched from source. Retrieved {$chunkSize} rows");

                $chunkStartTime = microtime(true);
                $memoryMb = memory_get_usage(true) / 1024 / 1024;

                $isFirstChunk = !$hasData;
                $hasData = true;

                $fetchedCount += $chunkSize;

                if ($isFirstChunk) {
                    $fieldMappings = $entity['field_mappings'] ?? [];
                    
                    // Validation: The table MUST already exist (Schema-on-Configuration)
                    if (!\Illuminate\Support\Facades\Schema::connection($destConnection)->hasTable($tableName)) {
                        $errorMsg = "Target table [{$tableName}] does not exist. Please save 'Manage Fields' configuration first to generate the schema.";
                        Log::error("Sync [{$entityKey}]: {$errorMsg}");
                        
                        // Let's also update the SyncJob to failed
                        if ($syncJobId) {
                            \App\Models\SyncJob::where('id', $syncJobId)->update([
                                'status' => \App\Models\SyncJob::STATUS_FAILED,
                                'error_message' => $errorMsg,
                                'completed_at' => now(),
                            ]);
                        }
                        throw new \Exception($errorMsg);
                    }

                    if ($isFullSync) {
                        // Create staging table strictly from mappings
                        $this->schemaManager->ensureTableFromMappings($destConnection, $stagingTableName, $fieldMappings);
                    }
                }

                // Clean and strictly filter unmapped fields
                $fieldMappings = $entity['field_mappings'] ?? [];
                $cleanData = $this->schemaManager->cleanRecords($recordsChunk, $fieldMappings);
                $cleanSize = count($cleanData);

                Log::info("STEP 2: Rows after transformation. {$cleanSize} rows");

                if ($cleanSize === 0) {
                    Log::error("Sync [{$entityKey}]: Transformed payload became empty. Stopping synchronization.");
                    throw new \Exception("Transformed payload became empty. Synchronization aborted.");
                }

                if ($isIncremental && !isset($cleanData[0]['id']) && !isset($cleanData[0]['NoteID']) && !isset($cleanData[0]['noteid']) && !isset($cleanData[0]['_row_hash'])) {
                    Log::warning("Sync [{$entityKey}]: Missing primary key or hash in payload for upsert. Data might be duplicated or fail.");
                }

                Log::info("STEP 3: Rows before entering executeUpsert(). {$cleanSize} rows");

                $fetchTimeMs = (microtime(true) - $chunkStartTime) * 1000;
                $writeStartTime = microtime(true);

                $entityConfig = $this->entityConfigService->get($entityKey);
                $primaryKeys = $entityConfig['primary_keys'] ?? null;

                if (empty($primaryKeys)) {
                    if (isset($cleanData[0]['id'])) {
                        $primaryKeys = ['id'];
                    } elseif (isset($cleanData[0]['NoteID'])) {
                        $primaryKeys = ['NoteID'];
                    } elseif (isset($cleanData[0]['noteid'])) {
                        $primaryKeys = ['noteid'];
                    }
                }

                $hasPrimaryKey = !empty($primaryKeys);

                $useUpsert = false;
                if ($hasPrimaryKey && $isIncremental && !$isAppendOnly) {
                    $useUpsert = true;
                } elseif (!$hasPrimaryKey && $isIncremental && !empty($customFilters) && !$isAppendOnly) {
                    // Custom filter sync with NO primary key -> use replaceTableData to replace the scope
                    $useUpsert = false;
                } elseif (!$hasPrimaryKey && $isIncremental && empty($customFilters) && !$isAppendOnly) {
                    // True incremental sync (LastModifiedDateTime) but NO primary key -> IMPOSSIBLE to safely apply delta
                    Log::error("Sync [{$entityKey}]: Cannot perform incremental sync without a primary key (id, NoteID, or configured primary_keys).");
                    throw new \Exception("Cannot perform incremental sync without a primary key. Please configure 'primary_keys' for this entity in Settings, or run a Full Sync.");
                }

                if ($isAppendOnly) {
                    Log::info("STEP ?: Using appendTableData because sync_mode is backdate (Append Only)");
                    $inserted = $this->appendTableData($destConnection, $writeTableName, $cleanData, $syncJobId, $insertedCount, $totalRecordsCount);
                } elseif ($useUpsert) {
                    Log::info("STEP ?: Using upsertTableData because primary key exists and isIncremental = true");
                    $inserted = $this->upsertTableData($destConnection, $tableName, $cleanData, $customFilters, $syncJobId, $isFirstChunk, $insertedCount, $totalRecordsCount, $primaryKeys);
                } else {
                    Log::info("STEP ?: Using replaceTableData because useUpsert = false");
                    $inserted = $this->replaceTableData($destConnection, $writeTableName, $cleanData, $customFilters, $syncJobId, $isFirstChunk, $insertedCount, $totalRecordsCount);
                }

                $writeTimeMs = (microtime(true) - $writeStartTime) * 1000;

                $insertedCount += $inserted;

                $actualCount = $this->schemaManager->getRowCount($destConnection, $writeTableName);
                Log::info("Sync [{$entityKey}]: Database verification after operation. Actual row count in DB ({$writeTableName}): {$actualCount}");

                $this->syncStateService->updateStatistics($schedule->id, $chunkSize, $inserted, 0, 0);

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
                        'records_updated' => $insertedCount, // Keep cumulative processed count during live sync
                        'rows_per_second' => $rowsPerSec,
                        'peak_memory_mb' => max($log->peak_memory_mb ?? 0, $memoryMb),
                    ]);
                } catch (\Exception $e) {
                    // Ignore if columns don't exist yet (migration not run)
                }
            };

            $progressCallback = function ($current, $total) use ($syncJobId, &$totalRecordsCount, &$lastProgressUpdate, $syncStartTime) {
                if ($total !== null) {
                    $totalRecordsCount = $total;
                }

                $now = microtime(true);
                // Buffer progress update: every 5 seconds or if finished
                if ($syncJobId && (($now - $lastProgressUpdate) >= 5.0 || $current === $total)) {
                    \App\Models\SyncJob::where('id', $syncJobId)->update([
                        'processed_rows' => $current,
                        'total_rows' => $totalRecordsCount,
                        'progress' => $totalRecordsCount > 0 ? min(99, (int) round(($current / $totalRecordsCount) * 100)) : ($current === $total && $total !== null ? 100 : 0),
                    ]);
                    $lastProgressUpdate = $now;
                }
            };

            // FETCH DATA BASED ON SOURCE CONNECTION TYPE
            $sourceType = $sourceConnection['type'] ?? 'odata';
            if ($sourceType === 'odata' || $sourceType === 'acumatica') {
                $this->odataService->setConnectionConfig($sourceConnection);
                if (isset($entity['batch_size']) && $entity['batch_size'] > 0) {
                    $this->odataService->setPageSize($entity['batch_size']);
                }

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
                // Using cursor streaming / chunking for low memory footprint
                $resolvedSourceConn = $this->connectionService->resolveConnectionName($sourceConnKey);
                if ($resolvedSourceConn) {
                    try {
                        DB::connection($resolvedSourceConn)->disableQueryLog();
                    } catch (\Throwable $e) {
                        // Ignore connection-specific logging errors
                    }
                }
                $query = DB::connection($resolvedSourceConn)->table($odataEntity);

                // Apply custom filters for DB sources
                if (!empty($customFilters)) {
                    $query->where(function ($q) use ($customFilters) {
                        foreach ($customFilters as $cf) {
                            $field = $cf['field'] ?? '';
                            $op = $cf['operator'] ?? 'eq';
                            $val = $cf['value'] ?? '';

                            if ($field && $val !== '') {
                                switch ($op) {
                                    case 'eq':
                                        $q->where($field, '=', $val);
                                        break;
                                    case 'ne':
                                        $q->where($field, '!=', $val);
                                        break;
                                    case 'contains':
                                        $q->where($field, 'like', '%' . $val . '%');
                                        break;
                                    case 'startswith':
                                        $q->where($field, 'like', $val . '%');
                                        break;
                                    case 'endswith':
                                        $q->where($field, 'like', '%' . $val);
                                        break;
                                    case 'gt':
                                        $q->where($field, '>', $val);
                                        break;
                                    case 'ge':
                                        $q->where($field, '>=', $val);
                                        break;
                                    case 'lt':
                                        $q->where($field, '<', $val);
                                        break;
                                    case 'le':
                                        $q->where($field, '<=', $val);
                                        break;
                                }
                            }
                        }
                    });
                }

                $totalRecordsCount = $query->count();
                $progressCallback(0, $totalRecordsCount);

                $chunkSize = min(1000, max(100, (int) ($entity['batch_size'] ?? 1000)));

                // Prefer streaming with cursor() for low memory footprint, with fallback to chunkById/chunk
                try {
                    $batch = [];
                    foreach ($query->cursor() as $record) {
                        $batch[] = (array) $record;
                        if (count($batch) >= $chunkSize) {
                            $processChunkCallback($batch);
                            $progressCallback($fetchedCount, $totalRecordsCount);
                            $batch = [];
                        }
                    }
                    if (!empty($batch)) {
                        $processChunkCallback($batch);
                        $progressCallback($fetchedCount, $totalRecordsCount);
                        $batch = [];
                    }
                } catch (\Throwable $cursorException) {
                    Log::warning("Sync [{$entityKey}]: Cursor stream failed, falling back to chunkById: " . $cursorException->getMessage());

                    $entityConfig = $this->entityConfigService->get($entityKey);
                    $primaryKeys = $entityConfig['primary_keys'] ?? null;
                    $chunkColumn = 'id';
                    if (!empty($primaryKeys) && is_array($primaryKeys) && count($primaryKeys) > 0) {
                        $chunkColumn = $primaryKeys[0];
                    }

                    try {
                        $query->chunkById($chunkSize, function ($records) use ($processChunkCallback, &$fetchedCount, $progressCallback, $totalRecordsCount) {
                            $arr = $records->map(fn($record) => (array) $record)->toArray();
                            $processChunkCallback($arr);
                            $progressCallback($fetchedCount, $totalRecordsCount);
                            unset($arr, $records);
                        }, $chunkColumn);
                    } catch (\Exception $e) {
                        // Fallback if no chunkColumn exists
                        $firstRow = DB::connection($resolvedSourceConn)->table($odataEntity)->first();
                        $firstCol = $firstRow ? array_key_first((array) $firstRow) : null;
                        if ($firstCol) {
                            $query->orderBy($firstCol);
                        }

                        $query->chunk($chunkSize, function ($records) use ($processChunkCallback, &$fetchedCount, $progressCallback, $totalRecordsCount) {
                            $arr = $records->map(fn($record) => (array) $record)->toArray();
                            $processChunkCallback($arr);
                            $progressCallback($fetchedCount, $totalRecordsCount);
                            unset($arr, $records);
                        });
                    }
                }
            } else if ($sourceType === 'sharepoint') {
                $provider = app(\App\Services\Connections\Providers\SharePointProvider::class);
                $client = $provider->createRuntimeConnection($sourceConnection['credentials'] ?? []);
                
                $parsed = $provider->parseObjectName($odataEntity, $sourceConnection['credentials'] ?? []);
                $driveId = $parsed['drive_id'];
                $itemId = $parsed['item_id'];
                $fileName = $parsed['file_name'];
                $sheet = $parsed['sheet'] ?? null;

                $data = $client->readExcelFile($driveId, $itemId, $fileName, $sheet);
                
                $rows = $data['rows'] ?? [];
                
                // If it's a Generator, we don't know the exact count in advance unless provided
                $totalRecordsCount = is_array($rows) ? count($rows) : null;
                $progressCallback(0, $totalRecordsCount);
                
                $chunkSize = $entity['batch_size'] ?? 1000;
                $currentChunk = [];
                
                foreach ($rows as $row) {
                    $currentChunk[] = $row;
                    if (count($currentChunk) >= $chunkSize) {
                        $processChunkCallback($currentChunk);
                        $progressCallback($fetchedCount, $totalRecordsCount);
                        $currentChunk = [];
                    }
                }
                
                if (!empty($currentChunk)) {
                    $processChunkCallback($currentChunk);
                    $progressCallback($fetchedCount, $totalRecordsCount);
                }
            }

            if ($fetchedCount === 0) {
                Log::warning("Sync [{$entityKey}]: 0 records were fetched from the source. Aborting sync early without any database modifications.");

                $this->syncStateService->updateWatermark($schedule->id, null, null);
                $this->syncStateService->completeSynchronization($schedule->id);

                $log->update([
                    'status' => 'success',
                    'records_fetched' => 0,
                    'records_inserted' => 0,
                    'duration_seconds' => round(microtime(true) - $startTime, 2),
                    'completed_at' => now(),
                ]);
                if ($syncJobId) {
                    \App\Models\SyncJob::where('id', $syncJobId)->update([
                        'status' => \App\Models\SyncJob::STATUS_COMPLETED,
                        'progress' => 100,
                        'processed_rows' => 0,
                        'total_rows' => 0,
                        'finished_at' => now()
                    ]);
                }
                return $log;
            }

            if ($isFullSync && $hasData) {
                // Verify staging table has data
                $stagingCount = $this->schemaManager->getRowCount($destConnection, $stagingTableName);
                if ($stagingCount === 0) {
                    throw new \Exception("Staging table [{$stagingTableName}] is empty after full sync. Aborting replacement to prevent data loss.");
                }

                Log::info("Sync [{$entityKey}]: Performing atomic replacement from staging to production.");

                DB::connection($destConnection)->transaction(function () use ($destConnection, $tableName, $stagingTableName) {
                    // Safest cross-provider atomic replacement: DELETE + INSERT SELECT
                    DB::connection($destConnection)->table($tableName)->delete();

                    $columns = \Illuminate\Support\Facades\Schema::connection($destConnection)->getColumnListing($stagingTableName);
                    $grammar = DB::connection($destConnection)->getQueryGrammar();
                    $columnsStr = implode(', ', array_map(function ($col) use ($grammar) {
                        return $grammar->wrap($col);
                    }, $columns));

                    $tableWrapped = $grammar->wrapTable($tableName);
                    $stagingWrapped = $grammar->wrapTable($stagingTableName);

                    DB::connection($destConnection)->statement("INSERT INTO {$tableWrapped} ({$columnsStr}) SELECT {$columnsStr} FROM {$stagingWrapped}");
                });

                // Cleanup
                \Illuminate\Support\Facades\Schema::connection($destConnection)->dropIfExists($stagingTableName);
                Log::info("Sync [{$entityKey}]: Atomic replacement completed successfully.");
            }

            $this->syncStateService->updateWatermark($schedule->id, null, null);
            $endTime = microtime(true);
            $duration = $endTime - $startTime;

            $finalRowCount = $this->schemaManager->getRowCount($destConnection, $tableName);
            $newRowsCount = $finalRowCount - $initialRowCount;
            if ($isFullSync) {
                // For a full sync, the entire table is replaced, so all rows are "new"
                $newRowsCount = $finalRowCount;
            }

            $log->update([
                'status' => 'success',
                'current_stage' => 'completed',
                'records_fetched' => $fetchedCount,
                'records_inserted' => $insertedCount,
                'records_updated' => max(0, $newRowsCount), // Overwrite with actual New Rows count at the end
                'duration_seconds' => $duration,
                'completed_at' => now(),
            ]);

            $this->syncStateService->completeSynchronization($schedule->id);
            $schedule->update(['last_run_at' => now()]);

            \Illuminate\Support\Facades\Cache::forget("entity_fields_{$entityKey}");
            \Illuminate\Support\Facades\Cache::forget("connection_{$destConnKey}_metadata");
            if (!empty($tableName)) {
                \Illuminate\Support\Facades\Cache::forget("connection_{$destConnKey}_structure_" . md5($tableName));
                \Illuminate\Support\Facades\Cache::forget("sync_metadata_table_{$destConnKey}_{$tableName}");
            }
            
            try {
                $connectionModel = \App\Models\Connection::where('key', $destConnKey)->first();
                if ($connectionModel && $connectionModel->is_analytics_source) {
                    \App\Jobs\RefreshSchemaCacheJob::dispatch($connectionModel->id);
                    Log::info("Sync [{$entityKey}]: Dispatched schema cache refresh for destination connection '{$destConnKey}'.");
                }
            } catch (\Exception $e) {
                Log::error("Sync [{$entityKey}]: Failed to dispatch schema cache refresh: " . $e->getMessage());
            }

            Log::info("Sync [{$entityKey}]: Completed — {$insertedCount} records inserted in {$log->duration_seconds}s.");
            if ($syncJobId) {
                \App\Models\SyncJob::where('id', $syncJobId)->update([
                    'status' => \App\Models\SyncJob::STATUS_COMPLETED,
                    'progress' => 100,
                    'processed_rows' => $insertedCount,
                    'total_rows' => $totalRecordsCount ?? $insertedCount,
                    'finished_at' => now()
                ]);
            }
            return $log;

        } catch (\App\Exceptions\SyncStopException $e) {
            if (isset($isFullSync) && $isFullSync && isset($stagingTableName) && isset($destConnection)) {
                try {
                    \Illuminate\Support\Facades\Schema::connection($destConnection)->dropIfExists($stagingTableName);
                } catch (\Throwable $cleanupException) {
                }
            }
            $endTime = microtime(true);
            $log->update([
                'status' => 'stopped',
                'current_stage' => 'stopped_by_user',
                'error_message' => $e->getMessage(),
                'duration_seconds' => $endTime - $startTime,
                'completed_at' => now(),
            ]);
            $this->syncStateService->failSynchronization($schedule->id);
            Log::info("Sync [{$entityKey}]: Stopped safely.");
            if ($syncJobId) {
                \App\Models\SyncJob::where('id', $syncJobId)->update([
                    'status' => \App\Models\SyncJob::STATUS_FAILED,
                    'error_message' => 'Stopped by user',
                    'finished_at' => now()
                ]);
            }
            return $log;

        } catch (\App\Exceptions\SyncCancelException $e) {
            if (isset($isFullSync) && $isFullSync && isset($stagingTableName) && isset($destConnection)) {
                try {
                    \Illuminate\Support\Facades\Schema::connection($destConnection)->dropIfExists($stagingTableName);
                } catch (\Throwable $cleanupException) {
                }
            }
            $endTime = microtime(true);
            $log->update([
                'status' => 'cancelled',
                'current_stage' => 'cancelled_by_user',
                'error_message' => $e->getMessage(),
                'duration_seconds' => $endTime - $startTime,
                'completed_at' => now(),
            ]);
            $this->syncStateService->failSynchronization($schedule->id);
            Log::info("Sync [{$entityKey}]: Cancelled forcefully.");
            if ($syncJobId) {
                \App\Models\SyncJob::where('id', $syncJobId)->update([
                    'status' => \App\Models\SyncJob::STATUS_FAILED,
                    'error_message' => 'Cancelled by user',
                    'finished_at' => now()
                ]);
            }
            return $log;

        } catch (\Throwable $e) {
            if (isset($isFullSync) && $isFullSync && isset($stagingTableName) && isset($destConnection)) {
                try {
                    \Illuminate\Support\Facades\Schema::connection($destConnection)->dropIfExists($stagingTableName);
                    Log::info("Sync [{$entityKey}]: Dropped staging table [{$stagingTableName}] after failure.");
                } catch (\Throwable $cleanupException) {
                    // Ignore cleanup errors
                }
            }
            try {
                $this->syncStateService->failSynchronization($schedule->id);
            } catch (\Exception $stateException) {
                // Ignore state transition errors to prevent masking original exception
            }

            Log::error("Sync [{$entityKey}]: Failed.", [
                'message' => $e->getMessage(),
                'trace' => mb_substr($e->getTraceAsString(), 0, 2000),
            ]);

            if (isset($log)) {
                $log->update([
                    'status' => 'failed',
                    'error_message' => mb_substr($e->getMessage(), 0, 15000),
                    'duration_seconds' => round(microtime(true) - $startTime, 2),
                    'completed_at' => now(),
                ]);
            }

            throw $e; // Rethrow to ensure Queue triggers failed() callback
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
        $entities = $this->entityConfigService->getAll();
        $results = [];
        $sorted = collect($entities)->sortBy(fn($e) => ($e['type'] ?? '') === 'master' ? 0 : 1);
        foreach ($sorted as $key => $entity) {
            $results[$key] = $this->syncEntity($key);
        }
        return $results;
    }

    private function replaceTableData(?string $destConnection, string $tableName, array $cleanData, array $customFilters = [], ?string $syncJobId = null, bool $isFirstChunk = false, int $baseInserted = 0, ?int $totalRecordsCount = null): int
    {
        $inserted = 0;
        $syncStartTime = microtime(true);
        $lastProgressUpdate = $syncStartTime;

        DB::connection($destConnection)->transaction(function () use ($destConnection, $tableName, $cleanData, &$inserted, $customFilters, $syncJobId, $isFirstChunk, $baseInserted, $totalRecordsCount, $syncStartTime, &$lastProgressUpdate) {
            if ($isFirstChunk) {
                if (!empty($customFilters)) {
                    $deletedCount = $this->deleteByFilterScope($destConnection, $tableName, $customFilters);
                    Log::info("Sync: Deleted {$deletedCount} existing rows from [{$tableName}] within filter scope before re-insert.");
                } else {
                    DB::connection($destConnection)->table($tableName)->delete();
                }
            }

            $columnCount = max(count($cleanData[0] ?? []), 1);

            $batchStrategy = clone $this->batchStrategyFactory->make($destConnection);
            $chunkSize = $batchStrategy->calculateChunkSize($columnCount);
            $chunks = array_chunk($cleanData, $chunkSize);
            
            foreach ($chunks as $chunk) {
                $inserted += $batchStrategy->executeInsert($destConnection, $tableName, $chunk);

                if ($syncJobId) {
                    $now = microtime(true);
                    if (($now - $lastProgressUpdate) >= 5.0) {
                        \App\Models\SyncJob::where('id', $syncJobId)->update([
                            'processed_rows' => $baseInserted + $inserted,
                            'total_rows' => $totalRecordsCount,
                            'progress' => $totalRecordsCount > 0 ? min(99, (int) round((($baseInserted + $inserted) / $totalRecordsCount) * 100)) : 0,
                        ]);
                        $lastProgressUpdate = $now;
                    }
                }
            }
        }, 5);
        return $inserted;
    }

    private function appendTableData(?string $destConnection, string $tableName, array $cleanData, ?string $syncJobId = null, int $baseInserted = 0, ?int $totalRecordsCount = null): int
    {
        $inserted = 0;
        $syncStartTime = microtime(true);
        $lastProgressUpdate = $syncStartTime;

        DB::connection($destConnection)->transaction(function () use ($destConnection, $tableName, $cleanData, &$inserted, $syncJobId, $baseInserted, $totalRecordsCount, $syncStartTime, &$lastProgressUpdate) {
            
            // NO DELETION Logic here! Just pure insert/append.
            
            $columnCount = max(count($cleanData[0] ?? []), 1);

            $batchStrategy = clone $this->batchStrategyFactory->make($destConnection);
            $chunkSize = $batchStrategy->calculateChunkSize($columnCount);
            $chunks = array_chunk($cleanData, $chunkSize);


            foreach ($chunks as $chunk) {
                if (isset($chunk[0]['tstamp'])) {
                    Log::debug('replaceTableData tstamp dump:', ['value' => $chunk[0]['tstamp'], 'type' => gettype($chunk[0]['tstamp'])]);
                }

                $inserted += $batchStrategy->executeInsert($destConnection, $tableName, $chunk);

                if ($syncJobId) {
                    $now = microtime(true);
                    if (($now - $lastProgressUpdate) >= 5.0) {
                        \App\Models\SyncJob::where('id', $syncJobId)->update([
                            'processed_rows' => $baseInserted + $inserted,
                            'total_rows' => $totalRecordsCount,
                            'progress' => $totalRecordsCount > 0 ? min(99, (int) round((($baseInserted + $inserted) / $totalRecordsCount) * 100)) : 0,
                        ]);
                        $lastProgressUpdate = $now;
                    }
                }
            }
        }, 5);
        return $inserted;
    }

    private function upsertTableData(?string $destConnection, string $tableName, array $cleanData, array $customFilters = [], ?string $syncJobId = null, bool $isFirstChunk = false, int $baseInserted = 0, ?int $totalRecordsCount = null, array $primaryKeys = ['id']): int
    {
        $insertedOrUpdated = 0;
        $syncStartTime = microtime(true);
        $lastProgressUpdate = $syncStartTime;

        DB::connection($destConnection)->transaction(function () use ($destConnection, $tableName, $cleanData, &$insertedOrUpdated, $customFilters, $syncJobId, $isFirstChunk, $baseInserted, $totalRecordsCount, $syncStartTime, &$lastProgressUpdate, $primaryKeys) {

            Log::info("STEP ?: Using executeUpsert with primary keys: " . implode(', ', $primaryKeys));

            $columnsToUpdate = array_keys($cleanData[0]);

            $exclude = $primaryKeys;
            $exclude[] = '_sync_id';

            $lowerExclude = array_map('strtolower', $exclude);
            $columnsToUpdate = array_filter($columnsToUpdate, function ($col) use ($lowerExclude) {
                return !in_array(strtolower($col), $lowerExclude);
            });
            $columnsToUpdate = array_values($columnsToUpdate);

            $columnCount = max(count($cleanData[0] ?? []), 1);

            $batchStrategy = clone $this->batchStrategyFactory->make($destConnection);
            $chunkSize = $batchStrategy->calculateChunkSize($columnCount);

            $chunks = array_chunk($cleanData, $chunkSize);

            foreach ($chunks as $chunk) {
                $batchStrategy->executeUpsert($destConnection, $tableName, $chunk, $primaryKeys, $columnsToUpdate);
                $insertedOrUpdated += count($chunk);

                if ($syncJobId) {
                    $now = microtime(true);
                    if (($now - $lastProgressUpdate) >= 5.0) {
                        \App\Models\SyncJob::where('id', $syncJobId)->update([
                            'processed_rows' => $baseInserted + $insertedOrUpdated,
                            'total_rows' => $totalRecordsCount,
                            'progress' => $totalRecordsCount > 0 ? min(99, (int) round((($baseInserted + $insertedOrUpdated) / $totalRecordsCount) * 100)) : 0,
                        ]);
                        $lastProgressUpdate = $now;
                    }
                }
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
            $op = $cf['operator'] ?? 'eq';
            $val = $cf['value'] ?? '';

            if (!$field || $val === '')
                continue;

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

        if (!$hasCondition)
            return 0;
        return $query->delete();
    }

    private function bulkInsertData(?string $destConnection, string $tableName, array $cleanData, ?string $syncJobId = null, int $baseInserted = 0): int
    {
        $inserted = 0;
        $columnCount = max(count($cleanData[0] ?? []), 1);

        $batchStrategy = clone $this->batchStrategyFactory->make($destConnection);
        $chunkSize = $batchStrategy->calculateChunkSize($columnCount);
        $chunks = array_chunk($cleanData, $chunkSize);

        foreach ($chunks as $chunk) {
            $inserted += $batchStrategy->executeInsert($destConnection, $tableName, $chunk);
            if ($syncJobId) {
                \App\Models\SyncJob::where('id', $syncJobId)->update([
                    'processed_rows' => $baseInserted + $inserted,
                ]);
            }
        }
        return $inserted;
    }

    public function getDashboardStats(): array
    {
        $entities = $this->entityConfigService->getAll();
        $totalRecords = 0;
        $entityStats = [];
        $hasLogsTable = \Illuminate\Support\Facades\Schema::hasTable('sync_logs');

        foreach ($entities as $key => $entity) {
            $destConnKey = $entity['destination_connection'] ?? 'database_default';
            $destConnection = $destConnKey;

            $count = \Illuminate\Support\Facades\Cache::remember(
                "row_count_{$destConnection}_{$entity['table']}",
                60,
                fn() => $this->schemaManager->getRowCount($destConnection, $entity['table'])
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
                'table' => $entity['table'],
                'type' => $entity['type'],
                'records' => $count,
                'last_sync' => $lastSync,
                'table_exists' => $this->schemaManager->tableExists($destConnection, $entity['table']),
            ];
        }

        $successCount = $hasLogsTable ? SyncLog::successful()->count() : 0;
        $failedCount = $hasLogsTable ? SyncLog::failed()->count() : 0;
        $runningCount = $hasLogsTable ? SyncLog::running()->count() : 0;

        return [
            'total_records' => $totalRecords,
            'total_entities' => count($entities),
            'master_entities' => collect($entities)->where('type', 'master')->count(),
            'transaction_entities' => collect($entities)->where('type', 'transaction')->count(),
            'running_jobs' => $runningCount,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'entity_stats' => $entityStats,
        ];
    }
}
