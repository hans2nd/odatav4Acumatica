<?php

namespace App\Http\Controllers;

use App\Jobs\SyncEntityJob;
use App\Models\SyncLog;
use App\Services\AcumaticaODataService;
use App\Services\EntityConfigService;
use App\Services\QueueWorkerService;
use App\Services\SchemaManager;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SyncController extends Controller
{
    protected SyncService $syncService;
    protected QueueWorkerService $queueWorker;
    protected EntityConfigService $entityConfigService;

    public function __construct(SyncService $syncService, QueueWorkerService $queueWorker, EntityConfigService $entityConfigService)
    {
        $this->syncService = $syncService;
        $this->queueWorker = $queueWorker;
        $this->entityConfigService = $entityConfigService;
    }

    /**
     * Sync all entities.
     *
     * If a queue worker is available (QUEUE_CONNECTION != sync), dispatches
     * the job to the queue for background processing.
     * Otherwise, falls back to synchronous execution.
     */
    public function syncAll(): JsonResponse
    {
        $entities = $this->entityConfigService->getAll();
        $connectionService = app(\App\Services\ConnectionService::class);
        $invalidEntities = [];

        foreach ($entities as $key => $config) {
            $sourceConnKey = !empty($config['source_connection']) ? $config['source_connection'] : 'acumatica_default';
            if (!$connectionService->get($sourceConnKey)) {
                $invalidEntities[] = $key;
            }
        }

        if (!empty($invalidEntities)) {
            $list = implode(', ', $invalidEntities);
            return response()->json([
                'success' => false,
                'message' => "Koneksi tidak ditemukan untuk entitas berikut: {$list}. Harap periksa menu Connection Management.",
                'error_type' => 'connection_missing'
            ], 400);
        }

        $sorted = collect($entities)->sortBy(fn($e) => ($e['type'] ?? '') === 'master' ? 0 : 1);
        $jobIds = [];

        foreach ($sorted as $key => $config) {
            $activeJob = \App\Models\SyncJob::where('entity', $key)
                ->whereIn('status', [\App\Models\SyncJob::STATUS_PENDING, \App\Models\SyncJob::STATUS_RUNNING])
                ->first();

            if ($activeJob) {
                continue; // Skip queuing duplicate job
            }

            // Check distributed lock
            $schedule = \App\Models\SyncSchedule::where('entity_key', $key)->first();
            if ($schedule) {
                $syncState = \App\Models\SyncState::where('sync_schedule_id', $schedule->id)->first();
                if ($syncState && $syncState->lock_owner !== null && (
                    $syncState->lock_expires_at === null || \Carbon\Carbon::parse($syncState->lock_expires_at)->isFuture()
                )) {
                    continue; // Skip, already locked by another process
                }
            }

            $jobRecord = \App\Models\SyncJob::create([
                'entity' => $key,
                'status' => \App\Models\SyncJob::STATUS_PENDING,
                'progress' => 0,
            ]);

            SyncEntityJob::dispatch($key, [], 'manual', $jobRecord->id);
            $jobIds[] = $jobRecord->id;
        }

        try {
            $this->queueWorker->startBackgroundWorker();
        } catch (\Throwable $e) {
        }

        return response()->json([
            'success' => true,
            'status' => 'queued',
            'message' => count($jobIds) . ' synchronization jobs have been queued.',
            'job_ids' => $jobIds,
        ], 202);
    }

    public function control(Request $request, int $id): JsonResponse
    {
        try {
            $log = SyncLog::find($id);

            if (!$log) {
                return response()->json([
                    'success' => false,
                    'message' => 'Log not found.',
                ], 404);
            }

            $action = $request->input('action');
            if (!in_array($action, ['pause', 'resume', 'stop', 'cancel'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid action.',
                ], 400);
            }

            // Signal running sync
            \Illuminate\Support\Facades\Cache::put("sync_control_log_{$id}", $action, 3600);

            // Give immediate UI feedback
            if ($action === 'resume') {
                $log->update(['status' => 'running']);
                \Illuminate\Support\Facades\Cache::forget("sync_control_log_{$id}");
            } elseif ($action === 'pause') {
                $log->update(['status' => 'paused', 'current_stage' => 'Pause Requested']);
            } elseif ($action === 'stop' || $action === 'cancel') {
                $log->update(['status' => 'stopped', 'current_stage' => 'Stop Requested']);

                // We intentionally do NOT proactively clear the sync state lock here.
                // Releasing another worker's lock while it is still running a transaction
                // will cause data corruption if another sync is started concurrently.
                // The running worker will see the 'sync_control_log_' cache signal,
                // finish its current transaction gracefully, and then release its own lock.
                
                try {
                    // Immediately mark any active SyncJob for this entity as failed
                    // so the UI reflects the stop request, but the background worker
                    // retains the lock until it cleanly exits.
                    \App\Models\SyncJob::where('entity', $log->entity_key)
                        ->whereIn('status', [\App\Models\SyncJob::STATUS_PENDING, \App\Models\SyncJob::STATUS_RUNNING])
                        ->update([
                            'status' => \App\Models\SyncJob::STATUS_FAILED,
                            'error_message' => 'Stopped manually by user via UI',
                            'finished_at' => now()
                        ]);
                } catch (\Exception $e) {
                    // Ignore state errors
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Signal '{$action}' sent to sync task #{$id}.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send control signal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync a single entity by key, optionally with custom filters.
     *
     * Dispatches to queue if available, otherwise runs synchronously.
     */
    public function syncEntity(Request $request, string $entity): JsonResponse
    {
        try {
            if (!$request->user()->hasPermission("{$entity}.sync") && !$request->user()->hasPermission('sync_engine.manage')) {
                return response()->json([
                    'success' => false,
                    'message' => "You do not have permission to sync {$entity}.",
                ], 403);
            }

            $config = $this->entityConfigService->get($entity);
            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => "Unknown entity: {$entity}",
                ], 404);
            }

            // custom_filters should be an array of associative arrays: [['field' => 'X', 'operator' => 'eq', 'value' => 'Y']]
            $customFilters = $request->input('custom_filters', []);
            $syncModeOverride = $request->input('sync_mode_override', 'default');
            if ($syncModeOverride === 'default') {
                $syncModeOverride = null;
            }
            $watermarkTypeOverride = $request->input('watermark_type_override');
            $watermarkColumnOverride = $request->input('watermark_column_override');

            if ($request->boolean('save_filters')) {
                \App\Models\SyncSchedule::updateOrCreate(
                    ['entity_key' => $entity],
                    ['custom_filters' => $customFilters]
                );
            }

            // Validate Connection before dispatching
            $sourceConnKey = !empty($config['source_connection']) ? $config['source_connection'] : 'acumatica_default';
            $sourceConnection = app(\App\Services\ConnectionService::class)->get($sourceConnKey);

            if (!$sourceConnection) {
                return response()->json([
                    'success' => false,
                    'message' => "Koneksi tidak ditemukan! Data koneksi ({$sourceConnKey}) mungkin telah dihapus atau belum dikonfigurasi. Harap periksa menu Connection Management.",
                    'error_type' => 'connection_missing'
                ], 400);
            }

            // Check distributed lock first
            $schedule = \App\Models\SyncSchedule::where('entity_key', $entity)->first();
            if ($schedule) {
                $syncState = \App\Models\SyncState::where('sync_schedule_id', $schedule->id)->first();
                if ($syncState && $syncState->lock_owner !== null && (
                    $syncState->lock_expires_at === null || \Carbon\Carbon::parse($syncState->lock_expires_at)->isFuture()
                )) {
                    // Lock is actively held! Find the most recent SyncJob to resume polling
                    $activeJob = \App\Models\SyncJob::where('entity', $entity)
                        ->orderByDesc('created_at')
                        ->first();

                    return response()->json([
                        'success' => false,
                        'status' => 'already_running',
                        'message' => 'Synchronization is already running.',
                        'schedule_id' => $schedule->id,
                        'job_id' => $activeJob ? $activeJob->id : null
                    ], 423);
                }
            }

            // Check if already running or pending for this entity
            $activeJob = \App\Models\SyncJob::where('entity', $entity)
                ->whereIn('status', [\App\Models\SyncJob::STATUS_PENDING, \App\Models\SyncJob::STATUS_RUNNING])
                ->first();

            if ($activeJob) {
                return response()->json([
                    'success' => false,
                    'status' => 'already_running',
                    'message' => 'Proses sinkronisasi untuk entitas ini sedang mengantre (pending) atau sedang berjalan. Harap tunggu hingga selesai.',
                    'job_id' => $activeJob->id
                ], 429);
            }

            $jobRecord = \App\Models\SyncJob::create([
                'entity' => $entity,
                'status' => \App\Models\SyncJob::STATUS_PENDING,
                'progress' => 0,
            ]);

            SyncEntityJob::dispatch($entity, $customFilters, 'manual', $jobRecord->id, $syncModeOverride, $watermarkTypeOverride, $watermarkColumnOverride);

            try {
                $this->queueWorker->startBackgroundWorker();
            } catch (\Throwable $e) {
            }

            return response()->json([
                'success' => true,
                'message' => 'Sync task dispatched to queue.',
                'job_id' => $jobRecord->id,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to queue sync for {$entity}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to start sync: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the current synchronization and lock status for a specific entity.
     */
    public function entitySyncStatus(Request $request, string $entity): JsonResponse
    {
        try {
            $config = $this->entityConfigService->get($entity);
            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => "Unknown entity: {$entity}",
                ], 404);
            }

            $activeJob = \App\Models\SyncJob::where('entity', $entity)
                ->whereIn('status', [\App\Models\SyncJob::STATUS_PENDING, \App\Models\SyncJob::STATUS_RUNNING])
                ->orderByDesc('created_at')
                ->first();

            $schedule = \App\Models\SyncSchedule::where('entity_key', $entity)->first();
            $isLocked = false;
            $lockOwner = null;
            $lockExpiresAt = null;

            if ($schedule) {
                $syncState = \App\Models\SyncState::where('sync_schedule_id', $schedule->id)->first();
                if ($syncState && $syncState->lock_owner !== null && (
                    $syncState->lock_expires_at === null || \Carbon\Carbon::parse($syncState->lock_expires_at)->isFuture()
                )) {
                    $isLocked = true;
                    $lockOwner = $syncState->lock_owner;
                    $lockExpiresAt = $syncState->lock_expires_at;
                }
            }

            $latestLog = SyncLog::where('entity_key', $entity)
                ->orderByDesc('id')
                ->first();

            return response()->json([
                'success' => true,
                'entity' => $entity,
                'is_running' => $activeJob !== null || $isLocked,
                'is_locked' => $isLocked,
                'lock_owner' => $lockOwner,
                'lock_expires_at' => $lockExpiresAt,
                'active_job' => $activeJob ? [
                    'id' => $activeJob->id,
                    'status' => $activeJob->status,
                    'progress' => $activeJob->progress,
                    'processed_rows' => $activeJob->processed_rows,
                    'total_rows' => $activeJob->total_rows,
                    'created_at' => $activeJob->created_at?->toIso8601String(),
                    'updated_at' => $activeJob->updated_at?->toIso8601String(),
                ] : null,
                'latest_log' => $latestLog ? [
                    'id' => $latestLog->id,
                    'status' => $latestLog->status,
                    'current_stage' => $latestLog->current_stage,
                    'records_processed' => $latestLog->records_processed,
                    'total_records' => $latestLog->total_records,
                    'updated_at' => $latestLog->updated_at?->toIso8601String(),
                ] : null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get entity sync status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Force reset/unlock stuck sync jobs and locks for an entity.
     */
    public function resetStuckJob(Request $request, string $entity): JsonResponse
    {
        try {
            if (!$request->user()->hasPermission("{$entity}.sync") && !$request->user()->hasPermission('sync_engine.manage')) {
                return response()->json([
                    'success' => false,
                    'message' => "You do not have permission to reset sync for {$entity}.",
                ], 403);
            }

            // 1. Mark active SyncJobs for this entity as failed
            $activeJobs = \App\Models\SyncJob::where('entity', $entity)
                ->whereIn('status', [\App\Models\SyncJob::STATUS_PENDING, \App\Models\SyncJob::STATUS_RUNNING])
                ->get();

            $jobsReset = 0;
            foreach ($activeJobs as $job) {
                $job->update([
                    'status' => \App\Models\SyncJob::STATUS_FAILED,
                    'error_message' => 'Reset / aborted by user due to stuck process or timeout',
                    'finished_at' => now(),
                ]);
                $jobsReset++;
            }

            // 2. Mark active SyncLog records as failed and signal cancel cache
            $activeLogs = SyncLog::where('entity_key', $entity)
                ->whereIn('status', ['running', 'pending', 'in_progress'])
                ->get();

            $logsReset = 0;
            foreach ($activeLogs as $log) {
                \Illuminate\Support\Facades\Cache::put('sync_control_log_' . $log->id, 'cancel', 3600);
                $log->update([
                    'status' => 'failed',
                    'current_stage' => 'Terminated (Stuck Reset)',
                    'error_message' => 'Process was aborted or reset due to timeout or stuck worker.',
                    'completed_at' => now(),
                ]);
                $logsReset++;
            }

            // 3. Clear distributed lock on SyncState
            $schedule = \App\Models\SyncSchedule::where('entity_key', $entity)->first();
            $lockReleased = false;
            if ($schedule) {
                $updated = \App\Models\SyncState::where('sync_schedule_id', $schedule->id)->update([
                    'lock_owner' => null,
                    'lock_acquired_at' => null,
                    'heartbeat_at' => null,
                    'lock_expires_at' => null,
                    'status' => 'Idle',
                ]);
                if ($updated > 0) {
                    $lockReleased = true;
                }
            }

            // 4. Clear Laravel unique job cache locks
            \Illuminate\Support\Facades\Cache::forget('laravel_unique_job:' . \App\Jobs\SyncEntityJob::class . ':' . $entity);
            \Illuminate\Support\Facades\Cache::forget('laravel_unique_job:' . \App\Jobs\SyncEntityJob::class . $entity);
            \Illuminate\Support\Facades\Cache::forget('laravel_unique_job:App\Jobs\SyncEntityJob:' . $entity);
            \Illuminate\Support\Facades\Cache::forget('laravel_unique_job:App\Jobs\SyncEntityJob' . $entity);

            return response()->json([
                'success' => true,
                'message' => "Kunci dan antrean sinkronisasi untuk {$entity} telah berhasil di-reset. Anda dapat memulai sinkronisasi kembali.",
                'details' => [
                    'jobs_reset' => $jobsReset,
                    'logs_reset' => $logsReset,
                    'lock_released' => $lockReleased,
                ]
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to reset stuck sync for {$entity}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mereset status sinkronisasi: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get recent sync logs as JSON.
     */
    public function logs(Request $request): JsonResponse
    {
        $query = SyncLog::orderByDesc('id');

        if ($request->has('entity')) {
            $query->where('entity_key', $request->input('entity'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $logs = $query->limit($request->input('limit', 15))->get();

        return response()->json(['logs' => $logs]);
    }



    /**
     * Logs page view.
     */
    public function logsPage(Request $request)
    {
        $query = SyncLog::query();

        if ($request->has('source')) {
            $query->where('source', $request->input('source'));
        }

        $logs = $query->orderByDesc('id')->paginate(15)->appends($request->all());

        return view('logs.index', [
            'logs' => $logs,
            'activeTab' => 'logs',
        ]);
    }

    public function saveFilters(Request $request, string $entity): JsonResponse
    {
        // Reject completely empty request bodies
        if (empty($request->getContent()) && empty($request->all())) {
            \Illuminate\Support\Facades\Log::warning("Rejected empty body request for {$entity}");
            return response()->json([
                'success' => false,
                'message' => 'Invalid request: Empty payload.',
            ], 400);
        }

        $customFilters = $request->input('custom_filters', []);
        
        // DEBUG: Log the incoming request data
        \Illuminate\Support\Facades\Log::info("Saving filters for {$entity}", [
            'raw_content' => $request->getContent(),
            'parsed_custom_filters' => $customFilters
        ]);

        \App\Models\SyncSchedule::updateOrCreate(
            ['entity_key' => $entity],
            ['custom_filters' => $customFilters]
        );

        return response()->json([
            'success' => true,
            'message' => 'Filters saved successfully.',
        ]);
    }

    /**
     * Get available fields for an entity to populate the filter dropdown.
     */
    public function entityFields(Request $request, string $entity, \App\Contracts\Metadata\MetadataSynchronizationServiceInterface $metadataService): JsonResponse
    {
        $config = $this->entityConfigService->get($entity);
        if (!$config) {
            return response()->json(['success' => false, 'message' => "Unknown entity: {$entity}"], 404);
        }

        $isRefresh = $request->boolean('refresh');

        $result = $metadataService->getMetadata($entity, $isRefresh);

        if (!$result->isSuccess) {
            return response()->json([
                'success' => false,
                'message' => $result->errorMessage
            ], 500);
        }

        $fields = array_column($result->fields, 'name');

        // Always include CompanyID if missing as it's a standard Acumatica filter
        if (!in_array('CompanyID', $fields) && !in_array('companyid', array_map('strtolower', $fields))) {
            $fields[] = 'CompanyID';
        }

        return response()->json(['success' => true, 'fields' => $fields]);
    }


}
