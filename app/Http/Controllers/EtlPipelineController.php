<?php

namespace App\Http\Controllers;

use App\Jobs\SyncEntityJob;
use App\Models\Connection;
use App\Models\SyncEntity;
use App\Models\SyncJob;
use App\Models\SyncLog;
use App\Models\SyncSchedule;
use App\Models\SyncState;
use App\Services\ConnectionService;
use App\Services\EntityConfigService;
use App\Services\QueueWorkerService;
use App\Services\SchemaManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EtlPipelineController extends Controller
{
    public function __construct(
        protected EntityConfigService $entityConfigService,
        protected ConnectionService $connectionService,
        protected SchemaManager $schemaManager,
        protected QueueWorkerService $queueWorker
    ) {}

    /**
     * Determine whether an entity configuration uses a SQL Server Stored Procedure.
     */
    protected function isStoredProcedureConfig(array $config): bool
    {
        $odataEntity = (string) ($config['odata_entity'] ?? '');
        return ($config['source_object_type'] ?? '') === 'stored_procedure'
            || !empty($config['sp_execution_mode'])
            || preg_match('/^(dbo\.)?(usp_|sp_)/i', trim($odataEntity, ' []')) === 1;
    }

    /**
     * Render the dedicated SQL Server -> MySQL ETL Pipeline dashboard.
     */
    public function index()
    {
        $allEntities = $this->entityConfigService->getAll();
        $connections = $this->connectionService->getAll();
        $schedules = SyncSchedule::all()->keyBy('entity_key');

        $pipelines = [];

        foreach ($allEntities as $key => $config) {
            $sourceConnKey = $config['source_connection'] ?? '';
            $sourceConn = $connections[$sourceConnKey] ?? null;
            $sourceType = strtolower((string) ($sourceConn['type'] ?? ''));

            $odataEntity = (string) ($config['odata_entity'] ?? '');
            $isSp = $this->isStoredProcedureConfig($config);

            // Include SQL Server sources and any Stored Procedure entities
            if ($sourceType !== 'sqlsrv' && !$isSp) {
                continue;
            }

            $destConnKey = $config['destination_connection'] ?? 'database_default';
            $destConn = $this->connectionService->resolveConnectionName($destConnKey);
            $tableName = $config['table'] ?? $config['table_name'] ?? '';
            $tableExists = $tableName !== '' ? $this->schemaManager->tableExists($destConn, $tableName) : false;

            $mysqlRows = 0;
            if ($tableExists) {
                try {
                    $mysqlRows = $this->schemaManager->getRowCount($destConn, $tableName);
                } catch (\Throwable $e) {
                    $mysqlRows = 0;
                }
            }

            // Automatically resolve completion SP display if source_object was accidentally set to usp_CompleteIncrementalSync
            $getProcName = $odataEntity;
            if (stripos($getProcName, 'usp_CompleteIncrementalSync') !== false) {
                $getProcName = 'dbo.usp_GetIncrementalSalesOrderSync';
            } elseif ($isSp && !str_contains($getProcName, '.')) {
                $getProcName = 'dbo.' . $getProcName;
            }

            $completionProcName = !empty($config['sp_completion_proc'])
                ? $config['sp_completion_proc']
                : 'dbo.usp_CompleteIncrementalSync';

            $schedule = $schedules->get($key);

            $pipelines[$key] = [
                'key'                => $key,
                'name'               => $config['description'] ?: $key,
                'type'               => $config['type'] ?? 'transaction',
                'is_sp'              => $isSp,
                'sp_execution_mode'  => $config['sp_execution_mode'] ?? ($isSp ? 'two_phase_delta' : 'table_or_view'),
                'get_proc'           => $getProcName,
                'source_object'      => $odataEntity,
                'completion_proc'    => $completionProcName,
                'schema_object'      => $config['sp_schema_object'] ?? $odataEntity,
                'delete_keys'        => $config['sp_delete_keys'] ?? 'CompanyID,OrderType,OrderNbr',
                'sync_process_name'  => $config['sp_sync_process_name'] ?? 'SO_TO_MYSQL_SYNC',
                'company_id'         => (int) ($config['sp_company_id'] ?? 2),
                'custom_start_date'  => $config['sp_custom_start_date'] ?? null,
                'watermark_col'      => $config['last_modified_column'] ?? null,
                'sync_mode'          => $config['sync_mode'] ?? 'incremental',
                'source_conn_key'    => $sourceConnKey,
                'source_conn_name'   => $sourceConn['name'] ?? $sourceConnKey,
                'dest_conn_key'      => $destConnKey,
                'dest_conn_name'     => $connections[$destConnKey]['name'] ?? $destConnKey,
                'table_name'         => $tableName,
                'table_exists'       => $tableExists,
                'mysql_rows'         => $mysqlRows,
                'batch_size'         => $config['batch_size'] ?? 2000,
                'schedule'           => $schedule,
            ];
        }

        // Sort so Stored Procedure pipelines appear first
        uasort($pipelines, fn($a, $b) => ($b['is_sp'] <=> $a['is_sp']));

        return view('etl.index', compact('pipelines', 'connections'));
    }

    /**
     * Fetch live SQL Server Watermark & Execution Log state + MySQL row count + active Laravel job state.
     */
    public function state(string $entity): JsonResponse
    {
        try {
            $config = $this->entityConfigService->get($entity);
            if (!$config) {
                return response()->json(['success' => false, 'message' => "Unknown entity: {$entity}"], 404);
            }

            $isSp = $this->isStoredProcedureConfig($config);
            $sourceConnKey = $config['source_connection'] ?? '';
            $destConnKey = $config['destination_connection'] ?? 'database_default';
            $resolvedSourceConn = $this->connectionService->resolveConnectionName($sourceConnKey);
            $resolvedDestConn = $this->connectionService->resolveConnectionName($destConnKey);

            $syncProcessName = !empty($config['sp_sync_process_name']) ? $config['sp_sync_process_name'] : 'SO_TO_MYSQL_SYNC';
            $companyId = !empty($config['sp_company_id']) ? (int) $config['sp_company_id'] : 2;
            $tableName = $config['table'] ?? $config['table_name'] ?? '';

            $watermarkControl = null;
            $executionLogs = [];
            $sqlServerError = null;

            if ($isSp && $resolvedSourceConn) {
                try {
                    $pdo = DB::connection($resolvedSourceConn)->getPdo();

                    $wmStmt = $pdo->prepare("
                        SELECT
                            SyncProcessName,
                            CompanyID,
                            CONVERT(VARCHAR(19), LastSuccessSyncTime, 120) AS LastSuccessSyncTime,
                            OverlapMinutes,
                            IsRunning,
                            LastRunStatus,
                            CONVERT(VARCHAR(19), UpdatedDateTime, 120) AS UpdatedDateTime
                        FROM dbo.SyncWatermarkControl
                        WHERE SyncProcessName = :proc_name AND CompanyID = :comp_id
                    ");
                    $wmStmt->execute([
                        ':proc_name' => $syncProcessName,
                        ':comp_id'   => $companyId,
                    ]);
                    $watermarkControl = $wmStmt->fetch(\PDO::FETCH_ASSOC) ?: null;

                    $logStmt = $pdo->prepare("
                        SELECT TOP 10
                            LogID,
                            SyncProcessName,
                            CONVERT(VARCHAR(19), StartTime, 120) AS StartTime,
                            CONVERT(VARCHAR(19), EndTime, 120) AS EndTime,
                            CONVERT(VARCHAR(19), WatermarkFrom, 120) AS WatermarkFrom,
                            CONVERT(VARCHAR(19), WatermarkTo, 120) AS WatermarkTo,
                            ChangedOrdersCount,
                            ExtractedRowsCount,
                            Status,
                            ErrorMessage
                        FROM dbo.SyncExecutionLog
                        WHERE SyncProcessName = :proc_name
                        ORDER BY LogID DESC
                    ");
                    $logStmt->execute([':proc_name' => $syncProcessName]);
                    $executionLogs = $logStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $sqlErr) {
                    $sqlServerError = $sqlErr->getMessage();
                }
            }

            $mysqlRows = 0;
            $tableExists = false;
            if ($tableName !== '' && $resolvedDestConn) {
                try {
                    $tableExists = $this->schemaManager->tableExists($resolvedDestConn, $tableName);
                    if ($tableExists) {
                        $mysqlRows = $this->schemaManager->getRowCount($resolvedDestConn, $tableName);
                    }
                } catch (\Throwable $e) {
                }
            }

            $activeJob = SyncJob::where('entity', $entity)
                ->whereIn('status', [SyncJob::STATUS_PENDING, SyncJob::STATUS_RUNNING])
                ->orderByDesc('created_at')
                ->first();

            $recentLogs = SyncLog::where('entity_key', $entity)
                ->orderByDesc('id')
                ->limit(10)
                ->get();
            $latestLog = $recentLogs->first();

            // Auto-heal stale/orphaned SyncJob records:
            // If activeJob exists, but there are no jobs in Laravel's queue and the sync log has already finished,
            // reconcile the SyncJob so the UI is not falsely stuck in "Sedang Berjalan" / 5%.
            if ($activeJob) {
                $hasQueuedWork = DB::table('jobs')->where('queue', 'default')->exists();
                $isLogFinished = $latestLog && in_array($latestLog->status, ['success', 'completed', 'failed']) && $latestLog->completed_at;

                if (!$hasQueuedWork && $isLogFinished) {
                    if ($latestLog->status === 'success' || $latestLog->status === 'completed') {
                        $finalRows = (int) ($latestLog->records_inserted ?: $latestLog->records_updated ?: $latestLog->records_fetched ?: 0);
                        $activeJob->update([
                            'status'         => SyncJob::STATUS_COMPLETED,
                            'progress'       => 100,
                            'processed_rows' => $finalRows,
                            'total_rows'     => $finalRows,
                            'finished_at'    => $latestLog->completed_at,
                        ]);
                    } else {
                        $activeJob->update([
                            'status'        => SyncJob::STATUS_FAILED,
                            'error_message' => $latestLog->error_message ?: 'Proses terhenti.',
                            'finished_at'   => $latestLog->completed_at,
                        ]);
                    }
                    $activeJob = null; // Reconciled!
                } elseif (!$hasQueuedWork && $activeJob->created_at && $activeJob->created_at->diffInMinutes(now()) >= 3) {
                    $activeJob->update([
                        'status'        => SyncJob::STATUS_FAILED,
                        'error_message' => 'Proses terhenti atau queue worker tidak aktif.',
                        'finished_at'   => now(),
                    ]);
                    $activeJob = null;
                }
            }

            $localLogs = $recentLogs->map(function (SyncLog $log) {
                return [
                    'id'               => $log->id,
                    'started_at'       => $log->started_at?->format('Y-m-d H:i:s'),
                    'completed_at'     => $log->completed_at?->format('Y-m-d H:i:s'),
                    'records_fetched'  => (int) ($log->records_fetched ?? 0),
                    'records_inserted' => (int) ($log->records_inserted ?? 0),
                    'records_updated'  => (int) ($log->records_updated ?? 0),
                    'duration_seconds' => $log->duration_seconds,
                    'status'           => $log->status,
                    'current_stage'    => $log->current_stage,
                    'source'           => $log->source,
                    'error_message'    => $log->error_message,
                ];
            })->values()->all();

            // Determine actual last completed sync time (from SQL Server SyncExecutionLog or local SyncLog)
            $lastCompletedAt = null;
            if (!empty($executionLogs)) {
                foreach ($executionLogs as $el) {
                    if (!empty($el['EndTime']) && strtoupper($el['Status'] ?? '') === 'SUCCESS') {
                        $lastCompletedAt = $el['EndTime'];
                        break;
                    }
                }
            }
            if (!$lastCompletedAt && $latestLog && $latestLog->completed_at) {
                $lastCompletedAt = $latestLog->completed_at->format('Y-m-d H:i:s');
            }

            return response()->json([
                'success'                 => true,
                'entity'                  => $entity,
                'is_sp'                   => $isSp,
                'has_sp_watermark_table'  => $watermarkControl !== null,
                'watermark_control'       => $watermarkControl,
                'execution_logs'          => $executionLogs,
                'local_logs'              => $localLogs,
                'last_completed_at'       => $lastCompletedAt,
                'sql_server_error'        => $sqlServerError,
                'mysql_table'             => $tableName,
                'table_exists'            => $tableExists,
                'mysql_rows'              => $mysqlRows,
                'active_job'              => $activeJob ? [
                    'id'             => $activeJob->id,
                    'status'         => $activeJob->status,
                    'progress'       => $activeJob->progress,
                    'processed_rows' => $activeJob->processed_rows,
                    'total_rows'     => $activeJob->total_rows,
                    'started_at'     => $activeJob->started_at?->toIso8601String(),
                ] : null,
                'latest_log'              => $latestLog ? [
                    'id'                => $latestLog->id,
                    'status'            => $latestLog->status,
                    'current_stage'     => $latestLog->current_stage,
                    'records_updated'   => $latestLog->records_updated,
                    'records_inserted'  => $latestLog->records_inserted,
                    'rows_per_second'   => round((float) ($latestLog->rows_per_second ?? 0), 1),
                    'duration_seconds'  => $latestLog->duration_seconds,
                    'completed_at'      => $latestLog->completed_at?->format('Y-m-d H:i:s'),
                    'error_message'     => $latestLog->error_message,
                ] : null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat status ETL: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Execute the ETL pipeline (Two-Phase Stored Procedure or SQL Server Table/View) in one click.
     */
    public function run(Request $request, string $entity): JsonResponse
    {
        try {
            $config = $this->entityConfigService->get($entity);
            if (!$config) {
                return response()->json(['success' => false, 'message' => "Unknown entity: {$entity}"], 404);
            }

            $isSp = $this->isStoredProcedureConfig($config);
            $runMode = $request->input('run_mode', 'auto_watermark'); // 'auto_watermark', 'custom_date', 'order_date_range', 'full'
            $customStartDate = trim((string) $request->input('custom_start_date', ''));
            $orderDateFrom = trim((string) $request->input('order_date_from', ''));
            $orderDateTo = trim((string) $request->input('order_date_to', ''));
            $keepWatermark = $request->boolean('keep_watermark', false);

            $customFilters = [];

            if ($isSp) {
                $syncProcessName = trim((string) $request->input('sync_process_name', $config['sp_sync_process_name'] ?? 'SO_TO_MYSQL_SYNC'));
                $companyId = (int) $request->input('company_id', $config['sp_company_id'] ?? 2);

                if ($runMode === 'order_date_range' && $orderDateFrom === '' && $orderDateTo === '') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Pilih minimal satu tanggal (OrderDate Dari atau Sampai) untuk mode Rentang OrderDate.',
                    ], 422);
                }

                // Update SyncEntity extra_attributes so configuration stays consistent
                $entityModel = SyncEntity::where('code', $entity)->first();
                if ($entityModel) {
                    $extra = $entityModel->extra_attributes ?? [];
                    $extra['sp_sync_process_name'] = $syncProcessName;
                    $extra['sp_company_id'] = $companyId;
                    $extra['sp_custom_start_date'] = ($runMode === 'custom_date' && $customStartDate !== '') ? $customStartDate : null;

                    // Ensure source_object points to the data-fetching SP if it was previously set to usp_CompleteIncrementalSync
                    $updates = ['extra_attributes' => $extra];
                    if (stripos((string) $entityModel->source_object, 'usp_CompleteIncrementalSync') !== false) {
                        $updates['source_object'] = 'usp_GetIncrementalSalesOrderSync';
                    }
                    $entityModel->update($updates);
                    Cache::forget('sync_entities_config_all');
                }

                // Build custom_filters for this execution
                $customFilters = [
                    ['field' => '@SyncProcessName', 'operator' => 'eq', 'value' => $syncProcessName],
                    ['field' => '@CompanyID', 'operator' => 'eq', 'value' => (string) $companyId],
                ];
                if ($runMode === 'custom_date' && $customStartDate !== '') {
                    $customFilters[] = ['field' => '@CustomStartDate', 'operator' => 'eq', 'value' => $customStartDate];
                } elseif ($runMode === 'order_date_range') {
                    if ($orderDateFrom !== '') {
                        $customFilters[] = ['field' => '@OrderDateFrom', 'operator' => 'eq', 'value' => $orderDateFrom];
                    }
                    if ($orderDateTo !== '') {
                        $customFilters[] = ['field' => '@OrderDateTo', 'operator' => 'eq', 'value' => $orderDateTo];
                    }
                    if ($keepWatermark) {
                        $customFilters[] = ['field' => '@KeepCurrentWatermark', 'operator' => 'eq', 'value' => '1'];
                    }
                }

                // Save default schedule filters WITHOUT locking date overrides permanently
                if ($request->boolean('save_default_params', true)) {
                    $defaultScheduleFilters = [
                        ['field' => '@SyncProcessName', 'operator' => 'eq', 'value' => $syncProcessName],
                        ['field' => '@CompanyID', 'operator' => 'eq', 'value' => (string) $companyId],
                    ];
                    SyncSchedule::updateOrCreate(
                        ['entity_key' => $entity],
                        ['custom_filters' => $defaultScheduleFilters]
                    );
                }
            } else {
                // Standard SQL Server Table / View: do NOT inject @SyncProcessName or @CompanyID
                if ($runMode === 'custom_date' && $customStartDate !== '' && !empty($config['last_modified_column'])) {
                    $customFilters[] = [
                        'field'    => $config['last_modified_column'],
                        'operator' => 'ge',
                        'value'    => $customStartDate,
                    ];
                } else {
                    $schedule = SyncSchedule::where('entity_key', $entity)->first();
                    $existingFilters = is_array($schedule?->custom_filters) ? $schedule->custom_filters : [];
                    foreach ($existingFilters as $cf) {
                        $fName = trim((string) ($cf['field'] ?? ''));
                        if ($fName !== '' && !str_starts_with($fName, '@')) {
                            $customFilters[] = $cf;
                        }
                    }
                }
            }

            // Check if already running in Laravel
            $activeJob = SyncJob::where('entity', $entity)
                ->whereIn('status', [SyncJob::STATUS_PENDING, SyncJob::STATUS_RUNNING])
                ->first();

            if ($activeJob) {
                $hasQueuedWork = DB::table('jobs')->where('queue', 'default')->exists();
                // If there is no job in queue table and activeJob was created > 60s ago, it is orphaned
                if (!$hasQueuedWork && $activeJob->created_at && $activeJob->created_at->diffInSeconds(now()) >= 30) {
                    $activeJob->update([
                        'status'        => SyncJob::STATUS_FAILED,
                        'error_message' => 'Superseded by new execution',
                        'finished_at'   => now(),
                    ]);
                    $activeJob = null;
                } else {
                    return response()->json([
                        'success' => false,
                        'status'  => 'already_running',
                        'message' => 'Sinkronisasi sedang berjalan. Memantau progres aktif...',
                        'job_id'  => $activeJob->id,
                    ], 429);
                }
            }

            if ($runMode === 'full') {
                $syncModeOverride = 'full';
            } elseif ($isSp) {
                $syncModeOverride = 'incremental';
            } else {
                $syncModeOverride = $config['sync_mode'] ?? 'incremental';
            }

            $jobRecord = SyncJob::create([
                'entity'   => $entity,
                'status'   => SyncJob::STATUS_PENDING,
                'progress' => 0,
            ]);

            SyncEntityJob::dispatch(
                $entity,
                $customFilters,
                'manual',
                $jobRecord->id,
                $syncModeOverride
            );

            try {
                $this->queueWorker->startBackgroundWorker();
            } catch (\Throwable $e) {
            }

            return response()->json([
                'success' => true,
                'message' => 'Sinkronisasi SQL Server → MySQL berhasil dimulai.',
                'job_id'  => $jobRecord->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulai sinkronisasi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update LastSuccessSyncTime & OverlapMinutes directly in dbo.SyncWatermarkControl on SQL Server.
     */
    public function updateWatermark(Request $request, string $entity): JsonResponse
    {
        try {
            $config = $this->entityConfigService->get($entity);
            if (!$config) {
                return response()->json(['success' => false, 'message' => "Unknown entity: {$entity}"], 404);
            }

            $validated = $request->validate([
                'sync_process_name'      => 'required|string|max:100',
                'company_id'             => 'required|integer',
                'last_success_sync_time' => 'required|string',
                'overlap_minutes'        => 'nullable|integer|min:0|max:1440',
            ]);

            $sourceConnKey = $config['source_connection'] ?? '';
            $resolvedSourceConn = $this->connectionService->resolveConnectionName($sourceConnKey);
            if (!$resolvedSourceConn) {
                return response()->json(['success' => false, 'message' => 'Koneksi SQL Server tidak ditemukan.'], 400);
            }

            $formattedTime = \Carbon\Carbon::parse($validated['last_success_sync_time'])->format('Y-m-d H:i:s.000');
            $overlap = (int) ($validated['overlap_minutes'] ?? 5);

            $pdo = DB::connection($resolvedSourceConn)->getPdo();
            $stmt = $pdo->prepare("
                IF EXISTS (SELECT 1 FROM dbo.SyncWatermarkControl WHERE SyncProcessName = :proc_name1 AND CompanyID = :comp_id1)
                BEGIN
                    UPDATE dbo.SyncWatermarkControl
                    SET LastSuccessSyncTime = :wm_time1,
                        OverlapMinutes = :overlap1,
                        IsRunning = 0,
                        LastRunStatus = 'READY',
                        UpdatedDateTime = SYSDATETIME()
                    WHERE SyncProcessName = :proc_name2 AND CompanyID = :comp_id2;
                END
                ELSE
                BEGIN
                    INSERT INTO dbo.SyncWatermarkControl (SyncProcessName, CompanyID, LastSuccessSyncTime, OverlapMinutes, IsRunning, LastRunStatus, UpdatedDateTime)
                    VALUES (:proc_name3, :comp_id3, :wm_time2, :overlap2, 0, 'READY', SYSDATETIME());
                END
            ");

            $stmt->execute([
                ':proc_name1' => $validated['sync_process_name'],
                ':comp_id1'   => (int) $validated['company_id'],
                ':wm_time1'   => $formattedTime,
                ':overlap1'   => $overlap,
                ':proc_name2' => $validated['sync_process_name'],
                ':comp_id2'   => (int) $validated['company_id'],
                ':proc_name3' => $validated['sync_process_name'],
                ':comp_id3'   => (int) $validated['company_id'],
                ':wm_time2'   => $formattedTime,
                ':overlap2'   => $overlap,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Watermark SQL Server ({$validated['sync_process_name']}) berhasil diatur ke {$formattedTime}.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui Watermark SQL Server: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Force unlock both SQL Server (dbo.SyncWatermarkControl + dbo.SyncExecutionLog) and MySQL locks.
     */
    public function unlock(Request $request, string $entity): JsonResponse
    {
        try {
            $config = $this->entityConfigService->get($entity);
            if (!$config) {
                return response()->json(['success' => false, 'message' => "Unknown entity: {$entity}"], 404);
            }

            // 1. Mark active SyncJobs as failed
            SyncJob::where('entity', $entity)
                ->whereIn('status', [SyncJob::STATUS_PENDING, SyncJob::STATUS_RUNNING])
                ->update([
                    'status'        => SyncJob::STATUS_FAILED,
                    'error_message' => 'Unlocked manually via ETL Pipeline UI',
                    'finished_at'   => now(),
                ]);

            // 2. Mark active SyncLogs as failed & signal cancel cache
            $activeLogs = SyncLog::where('entity_key', $entity)
                ->whereIn('status', ['running', 'pending', 'in_progress'])
                ->get();
            foreach ($activeLogs as $log) {
                Cache::put('sync_control_log_' . $log->id, 'cancel', 3600);
                $log->update([
                    'status'        => 'failed',
                    'current_stage' => 'Unlocked via ETL Pipeline',
                    'error_message' => 'Unlocked manually via ETL Pipeline UI',
                    'completed_at'  => now(),
                ]);
            }

            // 3. Clear SyncState lock
            $schedule = SyncSchedule::where('entity_key', $entity)->first();
            if ($schedule) {
                SyncState::where('sync_schedule_id', $schedule->id)->update([
                    'lock_owner'       => null,
                    'lock_acquired_at' => null,
                    'heartbeat_at'     => null,
                    'lock_expires_at'  => null,
                    'status'           => 'Idle',
                ]);
            }

            // 4. Clear unique job cache locks
            Cache::forget('laravel_unique_job:' . SyncEntityJob::class . ':' . $entity);
            Cache::forget('laravel_unique_job:' . SyncEntityJob::class . $entity);
            Cache::forget('laravel_unique_job:App\Jobs\SyncEntityJob:' . $entity);
            Cache::forget('laravel_unique_job:App\Jobs\SyncEntityJob' . $entity);

            // 5. Release SQL Server dbo.SyncWatermarkControl IsRunning = 0 & close RUNNING SyncExecutionLog (SP only)
            $sourceConnKey = $config['source_connection'] ?? '';
            if ($this->isStoredProcedureConfig($config) && $sourceConnKey) {
                $resolvedSourceConn = $this->connectionService->resolveConnectionName($sourceConnKey);
                if ($resolvedSourceConn) {
                    try {
                        $syncProcessName = !empty($config['sp_sync_process_name']) ? $config['sp_sync_process_name'] : 'SO_TO_MYSQL_SYNC';
                        $companyId = !empty($config['sp_company_id']) ? (int) $config['sp_company_id'] : 2;
                        $pdo = DB::connection($resolvedSourceConn)->getPdo();
                        $pdo->prepare("
                            UPDATE dbo.SyncWatermarkControl
                            SET IsRunning = 0, LastRunStatus = 'READY', UpdatedDateTime = SYSDATETIME()
                            WHERE SyncProcessName = :proc_name AND CompanyID = :comp_id;

                            UPDATE dbo.SyncExecutionLog
                            SET EndTime = SYSDATETIME(), Status = 'ABORTED', ErrorMessage = 'Unlocked via ETL Pipeline UI'
                            WHERE SyncProcessName = :proc_name2 AND Status = 'RUNNING';
                        ")->execute([
                            ':proc_name'  => $syncProcessName,
                            ':comp_id'    => $companyId,
                            ':proc_name2' => $syncProcessName,
                        ]);
                    } catch (\Throwable $sqlErr) {
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Status dan kunci antrean untuk {$entity} berhasil direset.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuka kunci: ' . $e->getMessage(),
            ], 500);
        }
    }
}
