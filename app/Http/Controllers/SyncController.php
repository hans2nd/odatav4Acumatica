<?php

namespace App\Http\Controllers;

use App\Jobs\SyncAllJob;
use App\Jobs\SyncEntityJob;
use App\Models\SyncLog;
use App\Services\AcumaticaODataService;
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

    public function __construct(SyncService $syncService, QueueWorkerService $queueWorker)
    {
        $this->syncService = $syncService;
        $this->queueWorker = $queueWorker;
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
        // Validate all connections before dispatching
        $entities = config('acumatica.entities', []);
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

        if ($this->queueWorker->isAsyncQueue()) {
            SyncAllJob::dispatch();
            $this->queueWorker->startBackgroundWorker();

            return response()->json([
                'success' => true,
                'async' => true,
                'message' => 'Sync All has been dispatched to the queue. Monitor progress on the dashboard.',
            ]);
        }

        // Fallback: synchronous execution
        set_time_limit(0);

        // Release session lock so the frontend can remain responsive
        if (request()->hasSession()) {
            request()->session()->save();
        }

        try {
            $results = $this->syncService->syncAll();

            $summary = [];
            foreach ($results as $key => $log) {
                $summary[] = [
                    'entity' => $key,
                    'status' => $log->status,
                    'records' => $log->records_inserted,
                    'duration' => $log->duration_seconds . 's',
                    'error' => $log->error_message,
                ];
            }

            $totalRecords = collect($results)->sum(fn($l) => $l->records_inserted);
            $failedCount = collect($results)->where('status', 'failed')->count();

            return response()->json([
                'success' => $failedCount === 0,
                'message' => $failedCount === 0
                    ? "Sync completed: {$totalRecords} total records."
                    : "Sync completed with {$failedCount} error(s).",
                'results' => $summary,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function stopQueue(int $id): JsonResponse
    {
        try {
            $log = SyncLog::find($id);

            if (!$log || $log->status !== 'running') {
                return response()->json([
                    'success' => false,
                    'message' => 'Log not found or sync is not currently running.',
                ], 404);
            }

            // Signal running sync to abort specifically for this log ID
            \Illuminate\Support\Facades\Cache::put("sync_abort_log_{$id}", true, 120);

            // Mark this specific log as failed immediately
            $log->update([
                'status' => 'failed',
                'error_message' => 'Aborted manually by user.',
                'completed_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Queue stopped successfully for sync task #{$id}.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to stop queue: ' . $e->getMessage(),
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
        $config = config("acumatica.entities.{$entity}");
        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => "Unknown entity: {$entity}",
            ], 404);
        }

        // custom_filters should be an array of associative arrays: [['field' => 'X', 'operator' => 'eq', 'value' => 'Y']]
        $customFilters = $request->input('custom_filters', []);

        if ($request->boolean('save_filters')) {
            \App\Models\SyncSchedule::updateOrCreate(
                ['entity_key' => $entity],
                ['custom_filters' => empty($customFilters) ? null : $customFilters]
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

        // Dispatch to queue for async processing
        if ($this->queueWorker->isAsyncQueue()) {
            // Initialize progress cache before dispatching so frontend can start polling
            \Illuminate\Support\Facades\Cache::put("sync_progress_{$entity}", [
                'current' => 0,
                'total' => null,
                'status' => 'queued',
            ], 3600);

            SyncEntityJob::dispatch($entity, $customFilters);
            $this->queueWorker->startBackgroundWorker();

            return response()->json([
                'success' => true,
                'async' => true,
                'entity' => $entity,
                'message' => "Sync for {$entity} has been dispatched to the queue.",
            ]);
        }

        // Fallback: synchronous execution
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        ignore_user_abort(true);

        // Release session lock so the frontend can poll /progress concurrently
        if ($request->hasSession()) {
            $request->session()->save();
        }

        try {
            $log = $this->syncService->syncEntity($entity, $customFilters);

            return response()->json([
                'success' => $log->status === 'success',
                'entity' => $entity,
                'status' => $log->status,
                'records' => $log->records_inserted,
                'duration' => $log->duration_seconds . 's',
                'error' => $log->error_message,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
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
     * Get current sync status (running jobs).
     */
    public function status(): JsonResponse
    {
        $running = SyncLog::running()->get();

        $latestPerEntity = SyncLog::latestPerEntity()
            ->orderByDesc('id')
            ->get()
            ->keyBy('entity_key');

        return response()->json([
            'running' => $running,
            'latest' => $latestPerEntity,
            'is_syncing' => $running->isNotEmpty(),
        ]);
    }

    /**
     * Send control signal to a running execution.
     */
    public function control(Request $request, int $logId): JsonResponse
    {
        $log = SyncLog::findOrFail($logId);

        $action = $request->input('action');
        if (!in_array($action, ['pause', 'resume', 'stop', 'cancel'])) {
            return response()->json(['success' => false, 'message' => 'Invalid action'], 400);
        }

        if (!in_array($log->status, ['running', 'paused'])) {
            return response()->json(['success' => false, 'message' => "Cannot {$action} a job in {$log->status} state."], 400);
        }

        $signalKey = 'sync_control_log_' . $logId;

        if ($action === 'resume') {
            \Illuminate\Support\Facades\Cache::forget($signalKey);
        } else {
            \Illuminate\Support\Facades\Cache::put($signalKey, $action, 3600); // Signal valid for 1 hour
        }

        // Optimistically update the UI status
        $message = "Execution successfully signaled to {$action}.";

        return response()->json([
            'success' => true,
            'message' => $message,
            'status' => $action === 'resume' ? 'running' : ($action === 'pause' ? 'paused' : $action)
        ]);
    }

    /**
     * Get real-time sync progress for an entity.
     */
    public function progress(string $entity): JsonResponse
    {
        $progress = \Illuminate\Support\Facades\Cache::get("sync_progress_{$entity}");

        return response()->json([
            'success' => true,
            'progress' => $progress
        ]);
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
        $customFilters = $request->input('custom_filters', []);

        \App\Models\SyncSchedule::updateOrCreate(
            ['entity_key' => $entity],
            ['custom_filters' => empty($customFilters) ? null : $customFilters]
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
        $config = config("acumatica.entities.{$entity}");
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
