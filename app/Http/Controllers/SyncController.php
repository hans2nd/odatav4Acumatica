<?php

namespace App\Http\Controllers;

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

        $sorted = collect($entities)->sortBy(fn($e) => ($e['type'] ?? '') === 'master' ? 0 : 1);
        $jobIds = [];

        foreach ($sorted as $key => $config) {
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

                // We also proactively clear the sync state lock if possible so user can restart immediately
                try {
                    $schedule = \App\Models\SyncSchedule::where('entity_key', $log->entity_key)->first();
                    if ($schedule) {
                        $syncStateService = app(\App\Services\Contracts\SyncStateServiceInterface::class);
                        $syncStateService->failSynchronization($schedule->id);
                        $syncStateService->releaseSynchronizationLock($schedule->id, $log->session_id);
                    }
                } catch (\Exception $e) {
                    // Ignore state errors if it's already released or failed
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

            $jobRecord = \App\Models\SyncJob::create([
                'entity' => $entity,
                'status' => \App\Models\SyncJob::STATUS_PENDING,
                'progress' => 0,
            ]);

            SyncEntityJob::dispatch($entity, $customFilters, 'manual', $jobRecord->id);

            try {
                $this->queueWorker->startBackgroundWorker();
            } catch (\Throwable $e) {
            }

            return response()->json([
                'success' => true,
                'status' => 'queued',
                'message' => 'Synchronization has been queued.',
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
        $customFilters = $request->input('custom_filters', []);

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
