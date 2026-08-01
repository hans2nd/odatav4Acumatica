<?php

namespace App\Http\Controllers;

use App\Filters\LogFilter;
use App\Models\SyncLog;
use App\Services\SyncLogExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogApiController extends Controller
{
    private LogFilter $logFilter;
    private SyncLogExportService $exportService;

    public function __construct(LogFilter $logFilter, SyncLogExportService $exportService)
    {
        $this->logFilter = $logFilter;
        $this->exportService = $exportService;
    }

    /**
     * Get paginated logs with advanced filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SyncLog::query();
        $query = $this->logFilter->apply($query, $request);

        // We use standard length-aware pagination
        // Using explicit select helps DB performance if the table has wide columns
        $logs = $query->select([
            'id', 'entity_key', 'entity_description', 'odata_entity',
            'type', 'source', 'status', 'current_stage', 'current_batch', 'total_batch',
            'records_fetched', 'records_inserted', 'rows_per_second', 'peak_memory_mb',
            'duration_seconds', 'started_at', 'completed_at', 'error_message'
        ])->paginate((int)$request->input('per_page', 15));

        return response()->json($logs);
    }

    /**
     * Get analytics and KPI data.
     */
    public function analytics(Request $request): JsonResponse
    {
        // For KPI Cards
        $totalExecutions = SyncLog::count();
        $running = SyncLog::whereIn('status', ['running', 'paused'])->count();
        $failed = SyncLog::where('status', 'failed')->count();
        
        $todayQuery = SyncLog::whereDate('started_at', now()->toDateString());
        $todayExecutions = (clone $todayQuery)->count();
        $todayFailed = (clone $todayQuery)->where('status', 'failed')->count();
        
        $peakRowsSec = SyncLog::max('rows_per_second') ?? 0;
        $peakMemory = SyncLog::max('peak_memory_mb') ?? 0;

        // Time-series data for the last 7 days (Trend)
        $sevenDaysAgo = now()->subDays(6)->startOfDay();
        
        $trendData = SyncLog::select(
                DB::raw('DATE(started_at) as date'),
                DB::raw('SUM(CASE WHEN status IN ("success", "completed") THEN 1 ELSE 0 END) as success_count'),
                DB::raw('SUM(CASE WHEN status IN ("failed", "cancelled", "stopped") THEN 1 ELSE 0 END) as failed_count')
            )
            ->where('started_at', '>=', $sevenDaysAgo)
            ->groupBy(DB::raw('DATE(started_at)'))
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'kpis' => [
                'total_executions' => $totalExecutions,
                'running' => $running,
                'failed' => $failed,
                'peak_rows_sec' => $peakRowsSec,
                'peak_memory_mb' => $peakMemory,
                'today_executions' => $todayExecutions,
                'today_failed' => $todayFailed,
            ],
            'trends' => $trendData
        ]);
    }

    /**
     * Get detailed log information for the Modal.
     */
    public function show(int $id): JsonResponse
    {
        $log = SyncLog::with('bottlenecks')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'log' => $log
        ]);
    }

    /**
     * Export filtered logs to CSV or Excel
     */
    public function export(Request $request)
    {
        $format = $request->input('format', 'csv');
        
        if ($format === 'excel') {
            return $this->exportService->exportExcel($request);
        }
        
        return $this->exportService->exportCsv($request);
    }

    /**
     * Handle bulk actions (pause, stop, resume, delete) for logs
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $action = $request->input('action');
        $targetType = $request->input('target_type'); // 'ids' or 'date_range'
        
        if (!in_array($action, ['pause', 'resume', 'stop', 'delete'])) {
            return response()->json(['success' => false, 'message' => 'Invalid action.'], 400);
        }

        $query = SyncLog::query();

        if ($targetType === 'ids') {
            $ids = $request->input('ids', []);
            if (empty($ids)) {
                return response()->json(['success' => false, 'message' => 'No logs selected.'], 400);
            }
            $query->whereIn('id', $ids);
        } elseif ($targetType === 'date_range') {
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            if (!$dateFrom || !$dateTo) {
                return response()->json(['success' => false, 'message' => 'Date range is required.'], 400);
            }
            // Include entire day for end date
            $query->whereBetween('started_at', [
                \Carbon\Carbon::parse($dateFrom)->startOfDay(),
                \Carbon\Carbon::parse($dateTo)->endOfDay()
            ]);
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid target type.'], 400);
        }

        $logs = $query->get();
        $count = $logs->count();

        if ($count === 0) {
            return response()->json(['success' => true, 'message' => 'No matching logs found.']);
        }

        if ($action === 'delete') {
            // Bulk delete
            // First release any locks for running/paused jobs just in case
            $syncStateService = app(\App\Services\Contracts\SyncStateServiceInterface::class);
            foreach ($logs as $log) {
                if (in_array($log->status, ['running', 'paused'])) {
                    try {
                        $schedule = \App\Models\SyncSchedule::where('entity_key', $log->entity_key)->first();
                        if ($schedule) {
                            $syncStateService->failSynchronization($schedule->id);
                            $syncStateService->releaseSynchronizationLock($schedule->id, $log->session_id);
                        }
                    } catch (\Exception $e) { }
                }
            }
            $query->delete(); // execute delete on builder
            return response()->json(['success' => true, 'message' => "Successfully deleted {$count} logs."]);
        }

        // For pause/resume/stop
        foreach ($logs as $log) {
            \Illuminate\Support\Facades\Cache::put("sync_control_log_{$log->id}", $action, 3600);
            
            if ($action === 'resume') {
                $log->update(['status' => 'running']);
                \Illuminate\Support\Facades\Cache::forget("sync_control_log_{$log->id}");
            } elseif ($action === 'pause') {
                $log->update(['status' => 'paused', 'current_stage' => 'Pause Requested']);
            } elseif ($action === 'stop') {
                $log->update(['status' => 'stopped', 'current_stage' => 'Stop Requested']);
                try {
                    $schedule = \App\Models\SyncSchedule::where('entity_key', $log->entity_key)->first();
                    if ($schedule) {
                        $syncStateService = app(\App\Services\Contracts\SyncStateServiceInterface::class);
                        $syncStateService->failSynchronization($schedule->id);
                        $syncStateService->releaseSynchronizationLock($schedule->id, $log->session_id);
                    }
                } catch (\Exception $e) { }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully sent '{$action}' signal to {$count} logs.",
        ]);
    }
}
