<?php

namespace App\Http\Controllers;

use App\Models\SyncLog;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    /**
     * Sync all entities.
     */
    public function syncAll(SyncService $syncService): JsonResponse
    {
        set_time_limit(0);

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
     * Sync a single entity by key.
     */
    public function syncEntity(SyncService $syncService, string $entity): JsonResponse
    {
        set_time_limit(0);

        $config = config("acumatica.entities.{$entity}");
        if (! $config) {
            return response()->json([
                'success' => false,
                'message' => "Unknown entity: {$entity}",
            ], 404);
        }

        try {
            $log = $syncService->syncEntity($entity);

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
}
