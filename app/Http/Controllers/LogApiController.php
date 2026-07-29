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
}
