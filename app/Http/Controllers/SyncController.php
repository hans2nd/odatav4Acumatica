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
        if ($this->queueWorker->isAsyncQueue()) {
            SyncAllJob::dispatch();
            $this->queueWorker->startBackgroundWorker();

            return response()->json([
                'success' => true,
                'async'   => true,
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
                    'entity'   => $key,
                    'status'   => $log->status,
                    'records'  => $log->records_inserted,
                    'duration' => $log->duration_seconds . 's',
                    'error'    => $log->error_message,
                ];
            }

            $totalRecords = collect($results)->sum(fn ($l) => $l->records_inserted);
            $failedCount  = collect($results)->where('status', 'failed')->count();

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
        if (! $config) {
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

        // Dispatch to queue for async processing
        if ($this->queueWorker->isAsyncQueue()) {
            // Initialize progress cache before dispatching so frontend can start polling
            \Illuminate\Support\Facades\Cache::put("sync_progress_{$entity}", [
                'current' => 0,
                'total'   => null,
                'status'  => 'queued',
            ], 3600);

            SyncEntityJob::dispatch($entity, $customFilters);
            $this->queueWorker->startBackgroundWorker();

            return response()->json([
                'success' => true,
                'async'   => true,
                'entity'  => $entity,
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
                'success'  => $log->status === 'success',
                'entity'   => $entity,
                'status'   => $log->status,
                'records'  => $log->records_inserted,
                'duration' => $log->duration_seconds . 's',
                'error'    => $log->error_message,
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
            'running'   => $running,
            'latest'    => $latestPerEntity,
            'is_syncing' => $running->isNotEmpty(),
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
            'logs'      => $logs,
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
    public function entityFields(Request $request, string $entity, SchemaManager $schemaManager, AcumaticaODataService $odataService): JsonResponse
    {
        $config = config("acumatica.entities.{$entity}");
        if (!$config) {
            return response()->json(['success' => false, 'message' => "Unknown entity: {$entity}"], 404);
        }

        $tableName = $config['table'];
        $connName = $config['destination_connection'] ?? 'database_default';
        $odataEntity = $config['odata_entity'];
        
        $isRefresh = $request->boolean('refresh');
        
        // If not forcing refresh and fields are already saved in config, return them instantly!
        if (!$isRefresh && !empty($config['available_fields'])) {
            return response()->json(['success' => true, 'fields' => $config['available_fields']]);
        }
        
        $cacheKey = "entity_fields_{$entity}";
        if ($isRefresh) {
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }

        $fields = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addDays(1), function () use ($entity, $tableName, $connName, $odataEntity, $odataService, $isRefresh) {
            $dbColumns = [];
            try {
                $firstRow = \Illuminate\Support\Facades\DB::connection($connName)->table($tableName)->first();
                $columns = $firstRow ? array_keys((array)$firstRow) : app(\App\Services\ConnectionService::class)->getTableColumns($connName, $tableName);
                
                if (!empty($columns)) {
                    $dbColumns = array_values(array_filter($columns, fn($col) => !in_array(strtolower($col), ['_sync_id', '_synced_at', '_row_hash'])));
                }
            } catch (\Exception $e) {
                // Table does not exist in local database yet
            }

            // If not forcing refresh and DB has columns, return them fast
            if (!$isRefresh && !empty($dbColumns)) {
                // Save the DB columns back to the config so it's even faster next time (bypasses DB query)
                app(\App\Services\EntityConfigService::class)->updateAvailableFields($entity, $dbColumns);
                return $dbColumns;
            }

            // Fetch from Acumatica
            $odataColumns = [];
            try {
                $sourceConnName = $config['source_connection'] ?? 'acumatica_odata';
                $sourceConnection = app(\App\Services\ConnectionService::class)->get($sourceConnName);
                if ($sourceConnection) {
                    $odataService->setConnectionConfig($sourceConnection);
                }

                if ($odataService->usesSessionAuth()) {
                    $odataService->login();
                }
                
                // Just get top 1 using fetchPage to avoid downloading all records
                // Pass false for $includeCount to avoid expensive COUNT(*) on large views
                $result = $odataService->fetchPage($odataEntity, 1, 0, '', false);
                $records = $result['value'] ?? [];
                
                if ($odataService->usesSessionAuth()) {
                    $odataService->logout();
                }

                if (!empty($records)) {
                    $cols = array_keys((array)$records[0]);
                    // Filter out OData metadata columns and our internals
                    $odataColumns = array_values(array_filter($cols, function ($col) {
                        return !str_starts_with($col, '@odata') && !in_array(strtolower($col), ['_sync_id', '_synced_at', '_row_hash']);
                    }));
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to fetch fields for {$odataEntity}", ['error' => $e->getMessage()]);
            }

            // Merge both DB and OData columns (in case DB has columns not returned in first OData record)
            $finalFields = array_values(array_unique(array_merge($dbColumns, $odataColumns)));
            
            // Explicitly include CompanyID as it is often a valid OData filter but might not be returned in the result set
            // Even if we couldn't fetch anything, providing CompanyID at least allows basic filtering
            if (!in_array('CompanyID', $finalFields) && !in_array('companyid', array_map('strtolower', $finalFields))) {
                $finalFields[] = 'CompanyID';
            }

            // Save the fetched fields back to the entity config so it is instantly available next time
            if (!empty($finalFields)) {
                app(\App\Services\EntityConfigService::class)->updateAvailableFields($entity, $finalFields);
            }

            return $finalFields;
        });

        // We return success even if only CompanyID is present, so the UI doesn't break
        return response()->json(['success' => true, 'fields' => $fields]);
    }


}
