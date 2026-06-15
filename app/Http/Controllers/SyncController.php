<?php

namespace App\Http\Controllers;

use App\Jobs\SyncAllJob;
use App\Jobs\SyncEntityJob;
use App\Models\SyncLog;
use App\Services\AcumaticaODataService;
use App\Services\SchemaManager;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SyncController extends Controller
{
    /**
     * Sync all entities.
     *
     * If a queue worker is available (QUEUE_CONNECTION != sync), dispatches
     * the job to the queue for background processing.
     * Otherwise, falls back to synchronous execution.
     */
    public function syncAll(SyncService $syncService): JsonResponse
    {
        if ($this->isAsyncQueue()) {
            SyncAllJob::dispatch();
            $this->startBackgroundWorker();

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
            $results = $syncService->syncAll();

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

    /**
     * Sync a single entity by key, optionally with custom filters.
     *
     * Dispatches to queue if available, otherwise runs synchronously.
     */
    public function syncEntity(Request $request, SyncService $syncService, string $entity): JsonResponse
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

        // Dispatch to queue for async processing
        if ($this->isAsyncQueue()) {
            // Initialize progress cache before dispatching so frontend can start polling
            \Illuminate\Support\Facades\Cache::put("sync_progress_{$entity}", [
                'current' => 0,
                'total'   => null,
                'status'  => 'queued',
            ], 3600);

            SyncEntityJob::dispatch($entity, $customFilters);
            $this->startBackgroundWorker();

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
            $log = $syncService->syncEntity($entity, $customFilters);

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
    public function logsPage()
    {
        $logs = SyncLog::orderByDesc('id')->paginate(15);

        return view('logs.index', [
            'logs'      => $logs,
            'activeTab' => 'logs',
        ]);
    }

    /**
     * Get available fields for an entity to populate the filter dropdown.
     */
    public function entityFields(string $entity, SchemaManager $schemaManager, AcumaticaODataService $odataService): JsonResponse
    {
        $config = config("acumatica.entities.{$entity}");
        if (!$config) {
            return response()->json(['success' => false, 'message' => "Unknown entity: {$entity}"], 404);
        }

        $tableName = $config['table'];
        
        // Strategy 1: Read from local database if table exists
        if (Schema::hasTable($tableName)) {
            $columns = Schema::getColumnListing($tableName);
            // Filter out internal columns
            $fields = array_values(array_filter($columns, fn($col) => !in_array(strtolower($col), ['_sync_id', '_synced_at'])));
            return response()->json(['success' => true, 'fields' => $fields]);
        }

        // Strategy 2: Fetch 1 record from Acumatica and extract keys
        try {
            $odataEntity = $config['odata_entity'];
            if ($odataService->usesSessionAuth()) {
                $odataService->login();
            }
            
            // Just get top 1 using fetchPage to avoid downloading all records
            $result = $odataService->fetchPage($odataEntity, 1, 0, '');
            $records = $result['value'] ?? [];
            
            if ($odataService->usesSessionAuth()) {
                $odataService->logout();
            }

            if (empty($records)) {
                return response()->json(['success' => false, 'message' => 'No records found in Acumatica to extract fields from.']);
            }

            // Extract columns, similar to SchemaManager
            $row = $records[0];
            $fields = array_values(array_filter(
                array_keys($row),
                fn (string $key) => ! str_starts_with($key, '@') && ! str_starts_with($key, 'odata.')
            ));

            // Sanitize them to match database column names
            $fields = array_map(fn($f) => $schemaManager->sanitizeColumnName($f), $fields);

            return response()->json(['success' => true, 'fields' => $fields]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Check if the queue is configured for async processing.
     * Returns false when using the 'sync' driver (no queue worker needed).
     */
    private function isAsyncQueue(): bool
    {
        return config('queue.default') !== 'sync';
    }

    /**
     * Spawns a background queue worker that processes jobs until the queue is empty.
     * This avoids needing to run a persistent Supervisor/Daemon manually.
     */
    private function startBackgroundWorker(): void
    {
        $basePath = base_path();
        $command = "php {$basePath}" . DIRECTORY_SEPARATOR . "artisan queue:work --stop-when-empty --timeout=1800 --memory=1024";

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Detached process in Windows
            pclose(popen("start /B {$command} > NUL 2>&1", "r"));
        } else {
            // Detached process in Linux/Unix
            exec("{$command} > /dev/null 2>&1 &");
        }
    }
}
