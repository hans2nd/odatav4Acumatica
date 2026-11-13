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

            $sourceType = $sourceConnection['type'] ?? 'odata';
            if ($sourceType === 'odata' || $sourceType === 'acumatica') {
                $this->odataService->setConnectionConfig($sourceConnection);
                if (isset($entity['batch_size']) && $entity['batch_size'] > 0) {
                    $this->odataService->setPageSize($entity['batch_size']);
                }
            }

            $isODataV4 = ($sourceType === 'odata' || $sourceType === 'acumatica')
                ? $this->odataService->isV4()
                : $this->isConnectionODataV4($sourceConnection);

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
                            $watermark = new \App\Services\Sync\Watermarks\TimestampWatermark($lastModifiedCol, $maxModifiedStr, $isODataV4);
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
                        if (str_starts_with(strtolower($valSafe), 'datetime\'')) {
                            $parsedDateStr = $valSafe;
                        } else {
                            try {
                                if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $valSafe, $m)) {
                                    $date = \Carbon\Carbon::create($m[3], $m[2], $m[1], 0, 0, 0);
                                } else {
                                    $date = \Carbon\Carbon::parse($valSafe);
                                }
                                $dateStr = $date->format('Y-m-d\TH:i:s.v');
                                $parsedDateStr = $isODataV4 ? "{$dateStr}Z" : "datetime'{$dateStr}'";
                            } catch (\Exception $e) {
                                $parsedDateStr = null;
                            }
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

            $isTwoPhaseSpSync = false;
            $spCompletionContext = [
                'needs_completion' => false,
                'completed'        => false,
            ];

            $lastTelemetryUpdate = $syncStartTime;
            $pendingStatsFetched = 0;
            $pendingStatsInserted = 0;

            $processChunkCallback = function ($recordsChunk) use (&$fetchedCount, &$insertedCount, &$hasData, &$totalRecordsCount, &$isTwoPhaseSpSync, &$lastTelemetryUpdate, &$pendingStatsFetched, &$pendingStatsInserted, $tableName, $isIncremental, $isAppendOnly, $customFilters, $syncJobId, $destConnection, $log, $schedule, $syncStartTime, $entityKey, $workerId, $isFullSync, $stagingTableName, $writeTableName, &$entity) {
                // Extend PHP execution timer on each active batch so ongoing syncs never get killed by timeout
                @set_time_limit(600);

                // Check Execution Control Signals (Pause, Stop, Cancel)
                $this->checkForControlSignals($log);

                $chunkSize = count($recordsChunk);
                if ($chunkSize === 0) {
                    return;
                }

                $chunkStartTime = microtime(true);
                $memoryMb = memory_get_usage(true) / 1024 / 1024;

                $isFirstChunk = !$hasData;
                $hasData = true;

                $fetchedCount += $chunkSize;
                $pendingStatsFetched += $chunkSize;

                $fieldMappings = $entity['field_mappings'] ?? [];

                if ($isFirstChunk) {
                    $sampleRow = (array) ($recordsChunk[0] ?? []);

                    // Auto-register discovered fields if Manage Fields has not been populated yet
                    if (empty($fieldMappings) && !empty($sampleRow)) {
                        try {
                            $this->entityConfigService->updateAvailableFields($entityKey, array_keys($sampleRow));
                            $refreshedEntity = $this->entityConfigService->get($entityKey);
                            if ($refreshedEntity) {
                                $entity = $refreshedEntity;
                                $fieldMappings = $entity['field_mappings'] ?? [];
                            }
                        } catch (\Throwable $e) {
                            // Non-fatal fallback
                        }
                    }

                    // Ensure target table and any newly mapped/discovered columns exist automatically without requiring manual Manage Fields save
                    if (!empty($fieldMappings)) {
                        $this->schemaManager->ensureTableFromMappings($destConnection, $tableName, $fieldMappings);
                    }
                    if (!empty($sampleRow)) {
                        $this->schemaManager->ensureTable($destConnection, $tableName, $sampleRow, $fieldMappings);
                    }

                    if ($isFullSync) {
                        if (!empty($fieldMappings)) {
                            $this->schemaManager->ensureTableFromMappings($destConnection, $stagingTableName, $fieldMappings);
                        }
                        if (!empty($sampleRow)) {
                            $this->schemaManager->ensureTable($destConnection, $stagingTableName, $sampleRow, $fieldMappings);
                        }
                    }
                }

                // Clean and map fields according to Manage Fields configuration
                $cleanData = $this->schemaManager->cleanRecords($recordsChunk, $fieldMappings);
                $cleanSize = count($cleanData);

                if ($cleanSize === 0) {
                    Log::error("Sync [{$entityKey}]: Transformed payload became empty. Stopping synchronization.");
                    throw new \Exception("Transformed payload became empty. Synchronization aborted.");
                }

                if ($isIncremental && !$isTwoPhaseSpSync && !isset($cleanData[0]['id']) && !isset($cleanData[0]['NoteID']) && !isset($cleanData[0]['noteid']) && !isset($cleanData[0]['_row_hash'])) {
                    Log::warning("Sync [{$entityKey}]: Missing primary key or hash in payload for upsert. Data might be duplicated or fail.");
                }

                $fetchTimeMs = (microtime(true) - $chunkStartTime) * 1000;
                $writeStartTime = microtime(true);

                if ($isTwoPhaseSpSync) {
                    // In Two-Phase Delta SP mode, changed orders (Result Set 1) have already been deleted from $writeTableName
                    // (or $writeTableName is a fresh staging table during Full Sync), so we batch-insert cleanly.
                    $inserted = $this->appendTableData($destConnection, $writeTableName, $cleanData, $syncJobId, $insertedCount, $totalRecordsCount);
                } else {
                    $primaryKeys = $entity['primary_keys'] ?? null;

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
                        Log::error("Sync [{$entityKey}]: Cannot perform incremental sync without a primary key (id, NoteID, or configured primary_keys).");
                        throw new \Exception("Cannot perform incremental sync without a primary key. Please configure 'primary_keys' for this entity in Settings, or run a Full Sync.");
                    }

                    if ($isAppendOnly) {
                        $inserted = $this->appendTableData($destConnection, $writeTableName, $cleanData, $syncJobId, $insertedCount, $totalRecordsCount);
                    } elseif ($useUpsert) {
                        $inserted = $this->upsertTableData($destConnection, $tableName, $cleanData, $customFilters, $syncJobId, $isFirstChunk, $insertedCount, $totalRecordsCount, $primaryKeys);
                    } else {
                        $inserted = $this->replaceTableData($destConnection, $writeTableName, $cleanData, $customFilters, $syncJobId, $isFirstChunk, $insertedCount, $totalRecordsCount);
                    }
                }

                $writeTimeMs = (microtime(true) - $writeStartTime) * 1000;

                $insertedCount += $inserted;
                $pendingStatsInserted += $inserted;

                unset($cleanData, $recordsChunk);

                // Throttle telemetry, lock heartbeat, and log DB updates to once every 2.5 seconds to eliminate I/O bottlenecks
                $now = microtime(true);
                if ($isFirstChunk || ($now - $lastTelemetryUpdate) >= 2.5) {
                    $this->syncStateService->heartbeatSynchronizationLock($schedule->id, $workerId);
                    $this->syncStateService->updateStatistics($schedule->id, $pendingStatsFetched, $pendingStatsInserted, 0, 0);
                    $pendingStatsFetched = 0;
                    $pendingStatsInserted = 0;

                    $elapsed = $now - $syncStartTime;
                    $rowsPerSec = $elapsed > 0 ? $fetchedCount / $elapsed : 0;

                    $this->bottleneckDetector->analyzeChunk($log, $fetchTimeMs, $writeTimeMs, $memoryMb);

                    try {
                        $log->update([
                            'current_stage' => 'processing_chunk',
                            'current_batch' => ceil($fetchedCount / max(1, $chunkSize)),
                            'records_updated' => $insertedCount,
                            'rows_per_second' => $rowsPerSec,
                            'peak_memory_mb' => max($log->peak_memory_mb ?? 0, $memoryMb),
                        ]);
                    } catch (\Exception $e) {
                        // Ignore if columns don't exist yet
                    }
                    $lastTelemetryUpdate = $now;
                }
            };

            $progressCallback = function ($current, $total) use ($syncJobId, &$totalRecordsCount, &$lastProgressUpdate, $syncStartTime) {
                if ($total !== null) {
                    $totalRecordsCount = $total;
                }

                $now = microtime(true);
                // Buffer progress update: every 2 seconds or if finished
                if ($syncJobId && (($now - $lastProgressUpdate) >= 2.0 || ($total !== null && $current === $total))) {
                    $calculatedProgress = 0;
                    if ($totalRecordsCount !== null && $totalRecordsCount > 0) {
                        $calculatedProgress = min(99, (int) round(($current / $totalRecordsCount) * 100));
                    } elseif ($current > 0) {
                        // Dynamic asymptotic progress indicator (5% -> 95%) when streaming from Stored Procedures without upfront COUNT(*)
                        $calculatedProgress = min(95, max(5, (int) round(90 * (1 - exp(-$current / 50000)))));
                    }

                    \App\Models\SyncJob::where('id', $syncJobId)->update([
                        'processed_rows' => $current,
                        'total_rows' => $totalRecordsCount ?? $current,
                        'progress' => $calculatedProgress,
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

                if ($this->isStoredProcedureEntity($entity, $odataEntity, $resolvedSourceConn)) {
                    $this->executeStoredProcedureSourceSync(
                        $resolvedSourceConn,
                        $destConnection,
                        $entityKey,
                        $entity,
                        $odataEntity,
                        $tableName,
                        $writeTableName,
                        $isFullSync,
                        $isIncremental,
                        $isAppendOnly,
                        $customFilters,
                        $processChunkCallback,
                        $progressCallback,
                        $fetchedCount,
                        $totalRecordsCount,
                        $isTwoPhaseSpSync,
                        $spCompletionContext
                    );
                } else {
                    $query = DB::connection($resolvedSourceConn)->table($odataEntity);

                    // Apply custom filters for DB sources
                    if (!empty($customFilters)) {
                        $query->where(function ($q) use ($customFilters) {
                            foreach ($customFilters as $cf) {
                                $field = $cf['field'] ?? '';
                                $op = $cf['operator'] ?? 'eq';
                                $val = $cf['value'] ?? '';

                                if ($field && $val !== '' && !str_starts_with(trim($field), '@')) {
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

                if (!empty($spCompletionContext['needs_completion']) && empty($spCompletionContext['completed'])) {
                    $this->completeSqlServerStoredProcedureSync($spCompletionContext, 0, true, null);
                }

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

            if (!empty($spCompletionContext['needs_completion']) && empty($spCompletionContext['completed'])) {
                $this->completeSqlServerStoredProcedureSync($spCompletionContext, $fetchedCount, true, null);
            }

            if ($pendingStatsFetched > 0 || $pendingStatsInserted > 0) {
                $this->syncStateService->updateStatistics($schedule->id, $pendingStatsFetched, $pendingStatsInserted, 0, 0);
                $pendingStatsFetched = 0;
                $pendingStatsInserted = 0;
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
            if (!empty($spCompletionContext['needs_completion']) && empty($spCompletionContext['completed'])) {
                try {
                    $this->completeSqlServerStoredProcedureSync($spCompletionContext, $fetchedCount ?? 0, false, 'Stopped by user: ' . $e->getMessage());
                } catch (\Throwable $spErr) {
                }
            }
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
            if (!empty($spCompletionContext['needs_completion']) && empty($spCompletionContext['completed'])) {
                try {
                    $this->completeSqlServerStoredProcedureSync($spCompletionContext, $fetchedCount ?? 0, false, 'Cancelled by user: ' . $e->getMessage());
                } catch (\Throwable $spErr) {
                }
            }
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
            if (!empty($spCompletionContext['needs_completion']) && empty($spCompletionContext['completed'])) {
                try {
                    $this->completeSqlServerStoredProcedureSync($spCompletionContext, $fetchedCount ?? 0, false, $e->getMessage());
                } catch (\Throwable $spErr) {
                }
            }
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
                    if (($now - $lastProgressUpdate) >= 2.0) {
                        $currentProcessed = $baseInserted + $insertedOrUpdated;
                        $calculatedProgress = 0;
                        if ($totalRecordsCount !== null && $totalRecordsCount > 0) {
                            $calculatedProgress = min(99, (int) round(($currentProcessed / $totalRecordsCount) * 100));
                        } elseif ($currentProcessed > 0) {
                            $calculatedProgress = min(95, max(5, (int) round(90 * (1 - exp(-$currentProcessed / 50000)))));
                        }

                        \App\Models\SyncJob::where('id', $syncJobId)->update([
                            'processed_rows' => $currentProcessed,
                            'total_rows' => $totalRecordsCount ?? $currentProcessed,
                            'progress' => $calculatedProgress,
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

    /**
     * Determine whether the given connection config uses OData v4 or v3.
     */
    public function isConnectionODataV4(?array $connection): bool
    {
        if (!$connection) {
            return config('acumatica.odata_path', 'odatav4') === 'odatav4';
        }

        $creds = $connection['credentials'] ?? [];
        $protocol = strtolower((string) ($creds['protocol'] ?? ''));

        if ($protocol === 'odata_v3' || $protocol === 'v3') {
            return false;
        }

        if ($protocol === 'odata_v4' || $protocol === 'v4') {
            return true;
        }

        $urlsToCheck = [
            $creds['odata_url'] ?? '',
            $creds['url'] ?? '',
            $creds['base_url'] ?? '',
        ];

        foreach ($urlsToCheck as $rawUrl) {
            if (empty($rawUrl)) continue;
            $url = strtolower($rawUrl);
            if (str_contains($url, 'odatav4')) {
                return true;
            }
            if (preg_match('#/(odata)(/|$|\?)#i', $url)) {
                return false;
            }
        }

        if (!empty($creds['odata_path'])) {
            return strtolower($creds['odata_path']) === 'odatav4';
        }

        return config('acumatica.odata_path', 'odatav4') === 'odatav4';
    }

    /**
     * Determine whether the entity is configured to pull from a Stored Procedure.
     */
    private function isStoredProcedureEntity(array $entity, string $odataEntity, ?string $resolvedSourceConn = null): bool
    {
        if (($entity['source_object_type'] ?? '') === 'stored_procedure') {
            return true;
        }

        if (!empty($entity['sp_execution_mode'])) {
            return true;
        }

        $cleanName = trim($odataEntity, " \t\n\r\0\x0B[]");
        if (preg_match('/^(dbo\.)?(usp_|sp_)/i', $cleanName)) {
            return true;
        }

        if ($resolvedSourceConn) {
            try {
                $driver = DB::connection($resolvedSourceConn)->getDriverName();
                if ($driver === 'sqlsrv') {
                    $escaped = str_replace("'", "''", $this->formatSqlServerProcedureName($odataEntity, false));
                    $row = DB::connection($resolvedSourceConn)->selectOne("SELECT type FROM sys.objects WHERE object_id = OBJECT_ID('{$escaped}') AND type IN ('P', 'PC')");
                    if ($row) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore check errors
            }
        }

        return false;
    }

    /**
     * Format a SQL Server stored procedure identifier safely.
     */
    private function formatSqlServerProcedureName(string $rawName, bool $withBrackets = true): string
    {
        $parts = explode('.', trim($rawName));
        $parts = array_values(array_filter(array_map(fn($p) => trim($p, " \t\n\r\0\x0B[]"), $parts), fn($p) => $p !== ''));

        if (count($parts) === 1) {
            array_unshift($parts, 'dbo');
        }

        if (!$withBrackets) {
            return implode('.', $parts);
        }

        return implode('.', array_map(fn($p) => '[' . str_replace(']', ']]', $p) . ']', $parts));
    }

    /**
     * Execute a SQL Server Stored Procedure synchronization (supports Two-Phase Delta SP & Single Result Set SP).
     */
    private function executeStoredProcedureSourceSync(
        string $resolvedSourceConn,
        string $destConnection,
        string $entityKey,
        array $entity,
        string $odataEntity,
        string $tableName,
        string $writeTableName,
        bool $isFullSync,
        bool $isIncremental,
        bool $isAppendOnly,
        array $customFilters,
        callable $processChunkCallback,
        callable $progressCallback,
        int &$fetchedCount,
        ?int &$totalRecordsCount,
        bool &$isTwoPhaseSpSync,
        array &$spCompletionContext
    ): void {
        $pdo = DB::connection($resolvedSourceConn)->getPdo();
        try {
            if (defined('PDO::SQLSRV_ATTR_QUERY_TIMEOUT')) {
                $pdo->setAttribute(\PDO::SQLSRV_ATTR_QUERY_TIMEOUT, 600);
            }
        } catch (\Throwable $e) {
            // Ignore attribute errors if driver does not support SQLSRV_ATTR_QUERY_TIMEOUT
        }

        $procBracketed = $this->formatSqlServerProcedureName($odataEntity, true);
        $procPlain = $this->formatSqlServerProcedureName($odataEntity, false);

        // Safeguard: If the entity was accidentally configured with the completion callback SP
        // (e.g. usp_CompleteIncrementalSync), automatically resolve it to the data-fetching SP (usp_GetIncremental...).
        if (stripos($procPlain, 'usp_CompleteIncrementalSync') !== false) {
            try {
                $getProcRow = DB::connection($resolvedSourceConn)->selectOne("SELECT TOP 1 SCHEMA_NAME(schema_id) AS sname, name FROM sys.procedures WHERE name LIKE 'usp_GetIncremental%' ORDER BY name");
                if ($getProcRow && !empty($getProcRow->name)) {
                    $resolvedGetName = ($getProcRow->sname ?: 'dbo') . '.' . $getProcRow->name;
                    Log::warning("Sync [{$entityKey}]: Source object was set to completion SP [{$procPlain}]; auto-redirecting to data SP [{$resolvedGetName}].");
                    $procBracketed = $this->formatSqlServerProcedureName($resolvedGetName, true);
                    $procPlain = $this->formatSqlServerProcedureName($resolvedGetName, false);
                }
            } catch (\Throwable $e) {
            }
        }

        // Determine SP mode: explicit config or auto-detect from sys.parameters
        $spMode = $entity['sp_execution_mode'] ?? '';
        if ($spMode === '') {
            try {
                $escaped = str_replace("'", "''", $procPlain);
                $params = DB::connection($resolvedSourceConn)->select("SELECT LOWER(name) AS pname FROM sys.parameters WHERE object_id = OBJECT_ID('{$escaped}')");
                $pNames = array_map(fn($r) => $r->pname ?? '', $params);
                if (in_array('@batchlogid', $pNames, true) && in_array('@newwatermark', $pNames, true)) {
                    $spMode = 'two_phase_delta';
                } else {
                    $spMode = 'single_result_set';
                }
            } catch (\Throwable $e) {
                $spMode = 'single_result_set';
            }
        }

        $chunkSize = min(1000, max(100, (int) ($entity['batch_size'] ?? 1000)));
        $fieldMappings = $entity['field_mappings'] ?? [];

        // Ensure target table exists before executing SP
        if (!\Illuminate\Support\Facades\Schema::connection($destConnection)->hasTable($tableName) && !empty($fieldMappings)) {
            $this->schemaManager->ensureTableFromMappings($destConnection, $tableName, $fieldMappings);
        }

        // Extract SP parameter overrides (@Param) and optional row-level post-filters from $customFilters
        $spParamOverrides = [];
        $rowLevelFilters = [];
        foreach ($customFilters as $cf) {
            $rawField = trim((string) ($cf['field'] ?? ''));
            $val = trim((string) ($cf['value'] ?? ''));
            $op = strtolower(trim((string) ($cf['operator'] ?? 'eq')));
            if ($rawField === '' || $val === '') {
                continue;
            }
            $cleanParam = strtolower(ltrim($rawField, '@'));
            if (str_starts_with($rawField, '@') || in_array($cleanParam, ['syncprocessname', 'companyid', 'customstartdate', 'orderdatefrom', 'orderdateto', 'keepcurrentwatermark', 'keepwatermark'], true)) {
                $spParamOverrides[$cleanParam] = $val;
            } else {
                $rowLevelFilters[] = ['field' => $rawField, 'operator' => $op, 'value' => $val];
            }
        }

        if ($spMode === 'two_phase_delta') {
            $isTwoPhaseSpSync = true;

            $syncProcessName = !empty($spParamOverrides['syncprocessname'])
                ? $spParamOverrides['syncprocessname']
                : (!empty($entity['sp_sync_process_name']) ? trim((string) $entity['sp_sync_process_name']) : 'SO_TO_MYSQL_SYNC');

            $companyId = isset($spParamOverrides['companyid']) && is_numeric($spParamOverrides['companyid'])
                ? (int) $spParamOverrides['companyid']
                : (!empty($entity['sp_company_id']) ? (int) $entity['sp_company_id'] : 2);

            $completionProc = !empty($entity['sp_completion_proc'])
                ? trim((string) $entity['sp_completion_proc'])
                : 'dbo.usp_CompleteIncrementalSync';

            $deleteKeyColsRaw = !empty($entity['sp_delete_keys'])
                ? (string) $entity['sp_delete_keys']
                : 'CompanyID,OrderType,OrderNbr';
            $deleteKeyCols = array_values(array_filter(array_map('trim', explode(',', $deleteKeyColsRaw))));

            $customStartDate = !empty($spParamOverrides['customstartdate'])
                ? $spParamOverrides['customstartdate']
                : (!empty($entity['sp_custom_start_date']) ? trim((string) $entity['sp_custom_start_date']) : null);

            if ($customStartDate !== null && $customStartDate !== '') {
                try {
                    $customStartDate = \Carbon\Carbon::parse($customStartDate)->format('Y-m-d H:i:s.v');
                } catch (\Throwable $e) {
                }
            } else {
                if ($isFullSync) {
                    $customStartDate = '2000-01-01 00:00:00.000';
                } elseif (!empty($rowLevelFilters)) {
                    foreach ($rowLevelFilters as $cf) {
                        $f = strtolower((string) ($cf['field'] ?? ''));
                        $op = strtolower((string) ($cf['operator'] ?? ''));
                        $val = trim((string) ($cf['value'] ?? ''));
                        if ($val !== '' && in_array($op, ['ge', 'gt', 'eq'], true) && (str_contains($f, 'date') || str_contains($f, 'time'))) {
                            try {
                                $customStartDate = \Carbon\Carbon::parse($val)->format('Y-m-d H:i:s.v');
                                break;
                            } catch (\Throwable $e) {
                            }
                        }
                    }
                }
            }

            $orderDateFrom = !empty($spParamOverrides['orderdatefrom']) ? trim((string) $spParamOverrides['orderdatefrom']) : null;
            if ($orderDateFrom !== null && $orderDateFrom !== '') {
                try {
                    $orderDateFrom = \Carbon\Carbon::parse($orderDateFrom)->format('Y-m-d');
                } catch (\Throwable $e) {
                }
            }

            $orderDateTo = !empty($spParamOverrides['orderdateto']) ? trim((string) $spParamOverrides['orderdateto']) : null;
            if ($orderDateTo !== null && $orderDateTo !== '') {
                try {
                    $orderDateTo = \Carbon\Carbon::parse($orderDateTo)->format('Y-m-d');
                } catch (\Throwable $e) {
                }
            }

            $keepWatermarkRaw = $spParamOverrides['keepcurrentwatermark'] ?? $spParamOverrides['keepwatermark'] ?? null;
            $keepCurrentWatermark = $keepWatermarkRaw !== null
                && in_array(strtolower(trim((string) $keepWatermarkRaw)), ['1', 'true', 'yes', 'on'], true);

            // Detect declared SP parameters so we only pass parameters supported by the physical Stored Procedure
            $declaredSpParams = [];
            try {
                $procPlainEscaped = str_replace("'", "''", $procPlain);
                $paramCheckStmt = $pdo->query("SELECT LOWER(name) AS pname FROM sys.parameters WHERE object_id = OBJECT_ID('{$procPlainEscaped}')");
                while ($pRow = $paramCheckStmt->fetch(\PDO::FETCH_ASSOC)) {
                    if (!empty($pRow['pname'])) {
                        $declaredSpParams[$pRow['pname']] = true;
                    }
                }
            } catch (\Throwable $paramErr) {
                $declaredSpParams['@customstartdate'] = true;
            }
            $hasCustomStartParam = !empty($declaredSpParams['@customstartdate']) || empty($declaredSpParams);
            $hasOrderDateFromParam = !empty($declaredSpParams['@orderdatefrom']);
            $hasOrderDateToParam = !empty($declaredSpParams['@orderdateto']);

            $preservedWatermark = null;
            if ($keepCurrentWatermark) {
                try {
                    $wmKeepStmt = $pdo->prepare("
                        SELECT CONVERT(VARCHAR(30), LastSuccessSyncTime, 121) AS LastSuccessSyncTime
                        FROM dbo.SyncWatermarkControl
                        WHERE SyncProcessName = :proc_name AND CompanyID = :comp_id
                    ");
                    $wmKeepStmt->execute([
                        ':proc_name' => $syncProcessName,
                        ':comp_id'   => $companyId,
                    ]);
                    $preservedWatermark = $wmKeepStmt->fetchColumn() ?: null;
                } catch (\Throwable $wmKeepErr) {
                }
            }

            $spCompletionContext = [
                'needs_completion'    => true,
                'completed'           => false,
                'source_connection'   => $resolvedSourceConn,
                'completion_proc'     => $completionProc,
                'sync_process_name'   => $syncProcessName,
                'company_id'          => $companyId,
                'batch_log_id'        => null,
                'new_watermark'       => null,
                'preserved_watermark' => $preservedWatermark,
            ];

            // Pre-flight safety: If a previous run was killed/aborted and left IsRunning = 1 in dbo.SyncWatermarkControl,
            // release the stale SQL Server watermark lock before starting the new Two-Phase execution.
            try {
                $pdo->prepare("
                    UPDATE dbo.SyncWatermarkControl
                    SET IsRunning = 0, LastRunStatus = 'IDLE', UpdatedDateTime = SYSDATETIME()
                    WHERE SyncProcessName = :proc_name AND CompanyID = :comp_id AND IsRunning = 1;

                    UPDATE dbo.SyncExecutionLog
                    SET EndTime = SYSDATETIME(), Status = 'ABORTED', ErrorMessage = 'Auto-cleaned stale RUNNING log before new sync'
                    WHERE SyncProcessName = :proc_name2 AND Status = 'RUNNING';
                ")->execute([
                    ':proc_name'  => $syncProcessName,
                    ':comp_id'    => $companyId,
                    ':proc_name2' => $syncProcessName,
                ]);
            } catch (\Throwable $preFlightErr) {
                // Non-fatal if table does not exist or schema differs
            }

            Log::info("Sync [{$entityKey}]: Executing Two-Phase Delta SP {$procPlain} (@SyncProcessName='{$syncProcessName}', @CompanyID={$companyId}, @CustomStartDate=" . ($customStartDate ?? 'NULL') . ", @OrderDateFrom=" . ($orderDateFrom ?? 'NULL') . ", @OrderDateTo=" . ($orderDateTo ?? 'NULL') . ", KeepWatermark=" . ($keepCurrentWatermark ? '1' : '0') . ")");

            $execParamLines = [
                '@SyncProcessName = :sync_process_name',
                '@CompanyID = :company_id',
            ];
            if ($hasCustomStartParam) {
                $execParamLines[] = '@CustomStartDate = :custom_start_date';
            }
            if ($hasOrderDateFromParam) {
                $execParamLines[] = '@OrderDateFrom = :order_date_from';
            }
            if ($hasOrderDateToParam) {
                $execParamLines[] = '@OrderDateTo = :order_date_to';
            }
            $execParamLines[] = '@BatchLogID = @BatchLogID OUTPUT';
            $execParamLines[] = '@NewWatermark = @NewWatermark OUTPUT';
            $execParamSql = implode(",\n                    ", $execParamLines);

            $sql = "
                SET NOCOUNT ON;
                DECLARE @BatchLogID BIGINT, @NewWatermark DATETIME2(3);
                EXEC {$procBracketed}
                    {$execParamSql};
                SELECT @BatchLogID AS BatchLogID, CONVERT(VARCHAR(30), @NewWatermark, 121) AS NewWatermark;
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':sync_process_name', $syncProcessName, \PDO::PARAM_STR);
            $stmt->bindValue(':company_id', $companyId, \PDO::PARAM_INT);
            if ($hasCustomStartParam) {
                if ($customStartDate !== null && $customStartDate !== '') {
                    $stmt->bindValue(':custom_start_date', $customStartDate, \PDO::PARAM_STR);
                } else {
                    $stmt->bindValue(':custom_start_date', null, \PDO::PARAM_NULL);
                }
            }
            if ($hasOrderDateFromParam) {
                if ($orderDateFrom !== null && $orderDateFrom !== '') {
                    $stmt->bindValue(':order_date_from', $orderDateFrom, \PDO::PARAM_STR);
                } else {
                    $stmt->bindValue(':order_date_from', null, \PDO::PARAM_NULL);
                }
            }
            if ($hasOrderDateToParam) {
                if ($orderDateTo !== null && $orderDateTo !== '') {
                    $stmt->bindValue(':order_date_to', $orderDateTo, \PDO::PARAM_STR);
                } else {
                    $stmt->bindValue(':order_date_to', null, \PDO::PARAM_NULL);
                }
            }

            $stmt->execute();

            try {
                // Result Set 1: #ChangedOrders (Keys to delete in target table)
                $changedOrders = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                // Guard in case Result Set 1 was already the @BatchLogID / @NewWatermark output row
                if (count($changedOrders) === 1 && array_key_exists('BatchLogID', $changedOrders[0]) && array_key_exists('NewWatermark', $changedOrders[0])) {
                    $spCompletionContext['batch_log_id'] = $changedOrders[0]['BatchLogID'] !== null ? (int) $changedOrders[0]['BatchLogID'] : null;
                    $spCompletionContext['new_watermark'] = $changedOrders[0]['NewWatermark'] ?? null;
                    $changedOrders = [];
                }

                $changedOrdersCount = count($changedOrders);
                Log::info("Sync [{$entityKey}]: SP Result Set 1 (#ChangedOrders) returned {$changedOrdersCount} order key(s).");

                // Phase 1: Delete changed orders from destination table when in incremental mode
                if (!$isFullSync && !$isAppendOnly && $changedOrdersCount > 0) {
                    if (\Illuminate\Support\Facades\Schema::connection($destConnection)->hasTable($writeTableName)) {
                        $this->ensureDeleteKeysIndex($destConnection, $writeTableName, $deleteKeyCols, $fieldMappings);
                        $deletedCount = $this->deleteChangedOrdersFromDestination(
                            $destConnection,
                            $writeTableName,
                            $changedOrders,
                            $deleteKeyCols,
                            $fieldMappings
                        );
                        Log::info("Sync [{$entityKey}]: Deleted {$deletedCount} existing row(s) in [{$writeTableName}] matching {$changedOrdersCount} changed order(s).");
                    }
                }

                // Result Set 2: Stream data rows from View (vw_UsrSyncSalesOrderInvoice)
                if ($stmt->nextRowset()) {
                    $batch = [];
                    while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
                        if (count($row) === 2 && array_key_exists('BatchLogID', $row) && array_key_exists('NewWatermark', $row)) {
                            $spCompletionContext['batch_log_id'] = $row['BatchLogID'] !== null ? (int) $row['BatchLogID'] : null;
                            $spCompletionContext['new_watermark'] = $row['NewWatermark'] ?? null;
                            continue;
                        }

                        if (!empty($rowLevelFilters) && !$this->matchesRowCustomFilters($row, $rowLevelFilters)) {
                            continue;
                        }

                        $batch[] = $row;
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
                }

                // Result Set 3: Capture @BatchLogID and @NewWatermark
                while ($stmt->nextRowset()) {
                    $outRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if (is_array($outRow) && (array_key_exists('BatchLogID', $outRow) || array_key_exists('NewWatermark', $outRow))) {
                        $spCompletionContext['batch_log_id'] = $outRow['BatchLogID'] !== null ? (int) $outRow['BatchLogID'] : null;
                        $spCompletionContext['new_watermark'] = $outRow['NewWatermark'] ?? null;
                    }
                }
            } finally {
                // If an exception occurred mid-stream before Result Set 3 was read, try advancing rowsets to capture BatchLogID
                if ($spCompletionContext['batch_log_id'] === null) {
                    try {
                        while ($stmt->nextRowset()) {
                            $outRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                            if (is_array($outRow) && array_key_exists('BatchLogID', $outRow)) {
                                $spCompletionContext['batch_log_id'] = $outRow['BatchLogID'] !== null ? (int) $outRow['BatchLogID'] : null;
                                $spCompletionContext['new_watermark'] = $outRow['NewWatermark'] ?? null;
                            }
                        }
                    } catch (\Throwable $ignore) {
                    }
                }
                try {
                    $stmt->closeCursor();
                } catch (\Throwable $ignore) {
                }
            }

            return;
        }

        // Standard Single Result Set Stored Procedure (supports binding @Param filters from card)
        $execAssignments = [];
        $execBindings = [];
        $paramIdx = 0;
        foreach ($customFilters as $cf) {
            $rawField = trim((string) ($cf['field'] ?? ''));
            $val = trim((string) ($cf['value'] ?? ''));
            if (str_starts_with($rawField, '@') && $val !== '' && preg_match('/^@[A-Za-z0-9_]+$/', $rawField)) {
                $placeholder = ":sp_param_{$paramIdx}";
                $execAssignments[] = "{$rawField} = {$placeholder}";
                $execBindings[$placeholder] = $val;
                $paramIdx++;
            }
        }
        $execParamClause = !empty($execAssignments) ? ' ' . implode(', ', $execAssignments) : '';

        Log::info("Sync [{$entityKey}]: Executing Standard Stored Procedure {$procPlain}{$execParamClause}");
        $stmt = $pdo->prepare("SET NOCOUNT ON; EXEC {$procBracketed}{$execParamClause};");
        foreach ($execBindings as $ph => $bVal) {
            $stmt->bindValue($ph, $bVal);
        }
        $stmt->execute();

        try {
            do {
                if ($stmt->columnCount() > 0) {
                    $batch = [];
                    while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
                        if (!empty($rowLevelFilters) && !$this->matchesRowCustomFilters($row, $rowLevelFilters)) {
                            continue;
                        }
                        $batch[] = $row;
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
                    break;
                }
            } while ($stmt->nextRowset());
        } finally {
            try {
                $stmt->closeCursor();
            } catch (\Throwable $ignore) {
            }
        }
    }

    /**
     * Evaluate optional row-level custom filters on rows streamed from a Stored Procedure.
     */
    private function matchesRowCustomFilters(array $row, array $filters): bool
    {
        foreach ($filters as $cf) {
            $field = $cf['field'] ?? '';
            $op = strtolower($cf['operator'] ?? 'eq');
            $targetVal = (string) ($cf['value'] ?? '');

            $actualVal = null;
            if (array_key_exists($field, $row)) {
                $actualVal = $row[$field];
            } else {
                foreach ($row as $k => $v) {
                    if (strcasecmp((string) $k, $field) === 0) {
                        $actualVal = $v;
                        break;
                    }
                }
            }

            $actualStr = (string) ($actualVal ?? '');
            $matched = match ($op) {
                'eq'         => strcasecmp($actualStr, $targetVal) === 0,
                'ne'         => strcasecmp($actualStr, $targetVal) !== 0,
                'contains'   => stripos($actualStr, $targetVal) !== false,
                'startswith' => stripos($actualStr, $targetVal) === 0,
                'endswith'   => str_ends_with(strtolower($actualStr), strtolower($targetVal)),
                'gt'         => is_numeric($actualStr) && is_numeric($targetVal) ? ((float) $actualStr > (float) $targetVal) : ($actualStr > $targetVal),
                'ge'         => is_numeric($actualStr) && is_numeric($targetVal) ? ((float) $actualStr >= (float) $targetVal) : ($actualStr >= $targetVal),
                'lt'         => is_numeric($actualStr) && is_numeric($targetVal) ? ((float) $actualStr < (float) $targetVal) : ($actualStr < $targetVal),
                'le'         => is_numeric($actualStr) && is_numeric($targetVal) ? ((float) $actualStr <= (float) $targetVal) : ($actualStr <= $targetVal),
                default      => true,
            };

            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    /**
     * Delete rows in destination table matching #ChangedOrders keys (e.g. CompanyID, OrderType, OrderNbr).
     * Groups by prefix keys (CompanyID, OrderType) and runs batched WHERE IN on the final key (OrderNbr) for maximum speed.
     */
    private function deleteChangedOrdersFromDestination(
        string $destConnection,
        string $tableName,
        array $changedOrders,
        array $deleteKeyCols,
        array $fieldMappings = []
    ): int {
        if (empty($changedOrders)) {
            return 0;
        }

        // Determine actual column names in the first row of $changedOrders
        $sampleRow = $changedOrders[0];
        $sourceCols = [];
        foreach ($deleteKeyCols as $configuredCol) {
            foreach (array_keys($sampleRow) as $actualKey) {
                if (strcasecmp($configuredCol, $actualKey) === 0) {
                    $sourceCols[] = $actualKey;
                    break;
                }
            }
        }

        if (empty($sourceCols)) {
            $sourceCols = array_keys($sampleRow);
        }

        // Map source key names to destination column names
        $destColsMap = [];
        foreach ($sourceCols as $srcCol) {
            $mappedDest = trim((string) ($fieldMappings[$srcCol]['destination_field'] ?? ''));
            $destColsMap[$srcCol] = $this->schemaManager->sanitizeColumnName($mappedDest !== '' ? $mappedDest : $srcCol);
        }

        $totalDeleted = 0;

        DB::connection($destConnection)->transaction(function () use ($destConnection, $tableName, $changedOrders, $sourceCols, $destColsMap, &$totalDeleted) {
            if (count($sourceCols) === 1) {
                $srcCol = $sourceCols[0];
                $destCol = $destColsMap[$srcCol];
                $values = array_values(array_unique(array_filter(array_column($changedOrders, $srcCol), fn($v) => $v !== null && $v !== '')));
                foreach (array_chunk($values, 500) as $chunk) {
                    $totalDeleted += DB::connection($destConnection)
                        ->table($tableName)
                        ->whereIn($destCol, $chunk)
                        ->delete();
                }
                return;
            }

            // Multiple keys (e.g. CompanyID, OrderType, OrderNbr):
            // Group by all prefix keys (CompanyID, OrderType) and batch whereIn on the last key (OrderNbr)
            $lastSrcCol = end($sourceCols);
            $lastDestCol = $destColsMap[$lastSrcCol];
            $prefixSrcCols = array_slice($sourceCols, 0, -1);

            $groups = [];
            foreach ($changedOrders as $row) {
                $lastVal = $row[$lastSrcCol] ?? null;
                if ($lastVal === null || $lastVal === '') {
                    continue;
                }
                $prefixValues = [];
                foreach ($prefixSrcCols as $pCol) {
                    $prefixValues[$destColsMap[$pCol]] = $row[$pCol] ?? null;
                }
                $groupKey = json_encode($prefixValues);
                $groups[$groupKey]['prefix'] = $prefixValues;
                $groups[$groupKey]['in_values'][] = $lastVal;
            }

            foreach ($groups as $group) {
                $inValues = array_values(array_unique($group['in_values']));
                foreach (array_chunk($inValues, 500) as $chunk) {
                    $q = DB::connection($destConnection)->table($tableName);
                    foreach ($group['prefix'] as $destCol => $val) {
                        $q->where($destCol, $val);
                    }
                    $totalDeleted += $q->whereIn($lastDestCol, $chunk)->delete();
                }
            }
        }, 3);

        return $totalDeleted;
    }

    /**
     * Ensure an index exists on the destination table for the delete key columns (e.g. CompanyID, OrderType, OrderNbr)
     * so Phase 1 DELETE queries execute via fast B-Tree Index Seek instead of full table scan.
     */
    private function ensureDeleteKeysIndex(
        string $destConnection,
        string $tableName,
        array $deleteKeyCols,
        array $fieldMappings = []
    ): void {
        if (empty($deleteKeyCols)) {
            return;
        }

        try {
            $driver = DB::connection($destConnection)->getDriverName();
            if (!in_array($driver, ['mysql', 'mariadb'], true)) {
                return;
            }

            $indexName = 'idx_' . substr(md5($tableName . '_' . implode('_', $deleteKeyCols)), 0, 16) . '_sync_keys';
            $existingIdx = DB::connection($destConnection)->select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]);
            if (!empty($existingIdx)) {
                return;
            }

            $colDefs = DB::connection($destConnection)->select("SHOW COLUMNS FROM `{$tableName}`");
            $colTypesByLower = [];
            $actualColNamesByLower = [];
            foreach ($colDefs as $c) {
                $f = (string) ($c->Field ?? '');
                $colTypesByLower[strtolower($f)] = strtolower((string) ($c->Type ?? ''));
                $actualColNamesByLower[strtolower($f)] = $f;
            }

            $indexParts = [];
            foreach ($deleteKeyCols as $srcCol) {
                $mappedDest = trim((string) ($fieldMappings[$srcCol]['destination_field'] ?? ''));
                $destCol = $this->schemaManager->sanitizeColumnName($mappedDest !== '' ? $mappedDest : $srcCol);
                $lower = strtolower($destCol);
                if (!isset($actualColNamesByLower[$lower])) {
                    continue;
                }
                $actualCol = $actualColNamesByLower[$lower];
                $type = $colTypesByLower[$lower] ?? '';
                // In MySQL/MariaDB, TEXT/BLOB columns require a prefix length in index definitions
                if (str_contains($type, 'text') || str_contains($type, 'blob')) {
                    $indexParts[] = "`{$actualCol}`(50)";
                } else {
                    $indexParts[] = "`{$actualCol}`";
                }
            }

            if (!empty($indexParts)) {
                $colsSql = implode(', ', $indexParts);
                DB::connection($destConnection)->statement("CREATE INDEX `{$indexName}` ON `{$tableName}` ({$colsSql})");
                Log::info("Sync: Created composite index [{$indexName}] on [{$tableName}] ({$colsSql}) for fast SP delta deletion.");
            }
        } catch (\Throwable $e) {
            Log::warning("Sync: Could not auto-create delete keys index on [{$tableName}]: " . $e->getMessage());
        }
    }

    /**
     * Call the completion Stored Procedure (e.g. dbo.usp_CompleteIncrementalSync) in the SQL Server Staging DB
     * to commit @NewWatermark on success or record FAILED status and release IsRunning=0 on failure.
     */
    private function completeSqlServerStoredProcedureSync(
        array &$spCompletionContext,
        int $extractedRowsCount,
        bool $isSuccess,
        ?string $errorMessage = null
    ): void {
        if (empty($spCompletionContext['needs_completion']) || !empty($spCompletionContext['completed'])) {
            return;
        }

        $resolvedSourceConn = $spCompletionContext['source_connection'] ?? null;
        $completionProc = $spCompletionContext['completion_proc'] ?? 'dbo.usp_CompleteIncrementalSync';
        $syncProcessName = $spCompletionContext['sync_process_name'] ?? 'SO_TO_MYSQL_SYNC';
        $companyId = (int) ($spCompletionContext['company_id'] ?? 2);
        $batchLogId = $spCompletionContext['batch_log_id'] ?? null;
        $newWatermark = $spCompletionContext['new_watermark'] ?? null;

        if (!$resolvedSourceConn || !$completionProc) {
            return;
        }

        // If the previous statement was interrupted mid-stream (e.g. stopped/cancelled/failed before Result Set 3),
        // purge and reconnect the source DB connection so the TDS buffer is immediately released and does not throw IMSSP busy errors.
        if (!$isSuccess || $batchLogId === null) {
            try {
                DB::purge($resolvedSourceConn);
                DB::connection($resolvedSourceConn)->reconnect();
            } catch (\Throwable $e) {
            }
        }

        $pdo = DB::connection($resolvedSourceConn)->getPdo();

        // Fallback: if an exception interrupted streaming before Result Set 3 was read,
        // look up the active RUNNING LogID and WatermarkTo from dbo.SyncExecutionLog
        if ($batchLogId === null || $newWatermark === null) {
            try {
                $lookupStmt = $pdo->prepare("
                    SELECT TOP 1 LogID, CONVERT(VARCHAR(30), WatermarkTo, 121) AS WatermarkTo
                    FROM dbo.SyncExecutionLog
                    WHERE SyncProcessName = :proc_name AND Status = 'RUNNING'
                    ORDER BY LogID DESC
                ");
                $lookupStmt->execute([':proc_name' => $syncProcessName]);
                if ($row = $lookupStmt->fetch(\PDO::FETCH_ASSOC)) {
                    $batchLogId = $batchLogId ?? (int) $row['LogID'];
                    $newWatermark = $newWatermark ?? $row['WatermarkTo'];
                }
            } catch (\Throwable $e) {
                // Ignore fallback lookup errors
            }
        }

        if ($batchLogId === null) {
            // Even if no BatchLogID row was found, ensure IsRunning = 0 on dbo.SyncWatermarkControl
            try {
                $pdo->prepare("
                    UPDATE dbo.SyncWatermarkControl
                    SET IsRunning = 0, LastRunStatus = :status, UpdatedDateTime = SYSDATETIME()
                    WHERE SyncProcessName = :proc_name AND CompanyID = :comp_id
                ")->execute([
                    ':status'    => $isSuccess ? 'SUCCESS' : 'FAILED',
                    ':proc_name' => $syncProcessName,
                    ':comp_id'   => $companyId,
                ]);
            } catch (\Throwable $e) {
            }
            Log::warning("Sync: Skipping {$completionProc} because BatchLogID was not generated (IsRunning reset to 0).");
            return;
        }

        if ($isSuccess && !empty($spCompletionContext['preserved_watermark'])) {
            $newWatermark = $spCompletionContext['preserved_watermark'];
        } elseif ($newWatermark === null) {
            $newWatermark = now()->utc()->format('Y-m-d H:i:s.v');
        }

        $procBracketed = $this->formatSqlServerProcedureName($completionProc, true);

        // Check whether the completion stored procedure actually exists in the target SQL Server database
        $completionProcExists = false;
        try {
            $checkStmt = $pdo->prepare("SELECT OBJECT_ID(:proc_name, 'P') AS proc_id");
            $checkStmt->execute([':proc_name' => $procBracketed]);
            $procRow = $checkStmt->fetch(\PDO::FETCH_ASSOC);
            $completionProcExists = !empty($procRow['proc_id']);
        } catch (\Throwable $e) {
            $completionProcExists = false;
        }

        if (!$completionProcExists) {
            // Fallback: update dbo.SyncExecutionLog and dbo.SyncWatermarkControl directly if they exist
            $statusLabel = $isSuccess ? 'SUCCESS' : 'FAILED';
            $truncatedError = ($errorMessage !== null && $errorMessage !== '') ? mb_substr($errorMessage, 0, 4000) : null;

            try {
                if ($pdo->query("SELECT OBJECT_ID('dbo.SyncExecutionLog', 'U')")->fetchColumn()) {
                    $logStmt = $pdo->prepare("
                        UPDATE dbo.SyncExecutionLog
                        SET EndTime = SYSDATETIME(),
                            ExtractedRowsCount = :extracted_rows_count,
                            Status = :status,
                            ErrorMessage = :error_message
                        WHERE LogID = :batch_log_id
                    ");
                    $logStmt->bindValue(':extracted_rows_count', $extractedRowsCount, \PDO::PARAM_INT);
                    $logStmt->bindValue(':status', $statusLabel, \PDO::PARAM_STR);
                    if ($truncatedError !== null) {
                        $logStmt->bindValue(':error_message', $truncatedError, \PDO::PARAM_STR);
                    } else {
                        $logStmt->bindValue(':error_message', null, \PDO::PARAM_NULL);
                    }
                    $logStmt->bindValue(':batch_log_id', (int) $batchLogId, \PDO::PARAM_INT);
                    $logStmt->execute();
                }
            } catch (\Throwable $e) {
                Log::warning("Sync: Fallback update on dbo.SyncExecutionLog failed: " . $e->getMessage());
            }

            try {
                if ($pdo->query("SELECT OBJECT_ID('dbo.SyncWatermarkControl', 'U')")->fetchColumn()) {
                    if ($isSuccess) {
                        $wmStmt = $pdo->prepare("
                            UPDATE dbo.SyncWatermarkControl
                            SET IsRunning = 0,
                                LastSuccessSyncTime = COALESCE(TRY_CONVERT(DATETIME2(3), :new_watermark), LastSuccessSyncTime),
                                LastRunStatus = 'SUCCESS',
                                UpdatedDateTime = SYSDATETIME()
                            WHERE SyncProcessName = :proc_name AND CompanyID = :comp_id
                        ");
                        $wmStmt->execute([
                            ':new_watermark' => (string) $newWatermark,
                            ':proc_name'     => $syncProcessName,
                            ':comp_id'       => $companyId,
                        ]);
                    } else {
                        $wmStmt = $pdo->prepare("
                            UPDATE dbo.SyncWatermarkControl
                            SET IsRunning = 0,
                                LastRunStatus = 'FAILED',
                                UpdatedDateTime = SYSDATETIME()
                            WHERE SyncProcessName = :proc_name AND CompanyID = :comp_id
                        ");
                        $wmStmt->execute([
                            ':proc_name' => $syncProcessName,
                            ':comp_id'   => $companyId,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Sync: Fallback update on dbo.SyncWatermarkControl failed: " . $e->getMessage());
            }

            $spCompletionContext['completed'] = true;
            Log::warning("Sync: Stored procedure {$completionProc} not found in target database; applied direct SyncExecutionLog/SyncWatermarkControl fallback (BatchLogID={$batchLogId}, Status={$statusLabel}, ExtractedRows={$extractedRowsCount}, Watermark={$newWatermark}).");
            return;
        }

        $sql = "
            SET NOCOUNT ON;
            EXEC {$procBracketed}
                @SyncProcessName = :sync_process_name,
                @CompanyID = :company_id,
                @BatchLogID = :batch_log_id,
                @NewWatermark = :new_watermark,
                @ExtractedRowsCount = :extracted_rows_count,
                @IsSuccess = :is_success,
                @ErrorMessage = :error_message;
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':sync_process_name', $syncProcessName, \PDO::PARAM_STR);
        $stmt->bindValue(':company_id', $companyId, \PDO::PARAM_INT);
        $stmt->bindValue(':batch_log_id', (int) $batchLogId, \PDO::PARAM_INT);
        $stmt->bindValue(':new_watermark', (string) $newWatermark, \PDO::PARAM_STR);
        $stmt->bindValue(':extracted_rows_count', $extractedRowsCount, \PDO::PARAM_INT);
        $stmt->bindValue(':is_success', $isSuccess ? 1 : 0, \PDO::PARAM_INT);
        if ($errorMessage !== null && $errorMessage !== '') {
            $stmt->bindValue(':error_message', mb_substr($errorMessage, 0, 4000), \PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':error_message', null, \PDO::PARAM_NULL);
        }

        $stmt->execute();
        $spCompletionContext['completed'] = true;

        Log::info("Sync: Executed {$completionProc} (BatchLogID={$batchLogId}, IsSuccess=" . ($isSuccess ? '1' : '0') . ", ExtractedRows={$extractedRowsCount}, Watermark={$newWatermark}).");
    }
}
