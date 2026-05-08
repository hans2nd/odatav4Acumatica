<?php

namespace App\Http\Controllers;

use App\Models\SyncLog;
use App\Services\AcumaticaODataService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(SyncService $syncService)
    {
        $dbConnected = true;
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $dbConnected = false;
        }

        if (!$dbConnected) {
            $stats = [
                'total_records'       => 0,
                'total_entities'      => count(config('acumatica.entities', [])),
                'master_entities'     => 0,
                'transaction_entities' => 0,
                'running_jobs'        => 0,
                'success_count'       => 0,
                'failed_count'        => 0,
                'entity_stats'        => [],
            ];
            $recentLogs = collect();
        } else {
            $stats = $syncService->getDashboardStats();

            $hasLogsTable = \Illuminate\Support\Facades\Schema::hasTable('sync_logs');
            $recentLogs = $hasLogsTable ? SyncLog::orderByDesc('id')
                ->limit(15)
                ->get() : collect();
        }

        $masterRecords = collect($stats['entity_stats'])
            ->where('type', 'master')
            ->sum('records');

        $transactionRecords = collect($stats['entity_stats'])
            ->where('type', 'transaction')
            ->sum('records');

        return view('dashboard', [
            'stats'              => $stats,
            'recentLogs'         => $recentLogs,
            'masterRecords'      => $masterRecords,
            'transactionRecords' => $transactionRecords,
            'activeTab'          => 'overview',
        ]);
    }

    public function connectionStatus(AcumaticaODataService $odataService): JsonResponse
    {
        // Test Database
        $defaultDb = config('database.default', 'sqlsrv');
        $dbNameLabel = $defaultDb === 'mysql' ? 'MySQL' : 'SQL Server';

        $dbStatus = ['connected' => false, 'ping_ms' => 0, 'message' => 'Not tested', 'type' => $dbNameLabel];
        try {
            $start = microtime(true);
            DB::connection($defaultDb)->getPdo();
            $elapsed = round((microtime(true) - $start) * 1000);
            $dbStatus = [
                'connected' => true,
                'ping_ms'   => $elapsed,
                'message'   => 'Connected',
                'type'      => $dbNameLabel,
            ];
        } catch (\Exception $e) {
            $dbStatus = [
                'connected' => false,
                'ping_ms'   => 0,
                'message'   => $e->getMessage(),
                'type'      => $dbNameLabel,
            ];
        }

        // Test Acumatica
        $acumaticaStatus = $odataService->testConnection();

        return response()->json([
            'database'   => $dbStatus,
            'acumatica'  => $acumaticaStatus,
        ]);
    }
}
