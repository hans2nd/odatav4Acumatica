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
        $stats = $syncService->getDashboardStats();

        $recentLogs = SyncLog::orderByDesc('id')
            ->limit(15)
            ->get();

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
        // Test SQL Server
        $sqlStatus = ['connected' => false, 'ping_ms' => 0, 'message' => 'Not tested'];
        try {
            $start = microtime(true);
            DB::connection('sqlsrv')->getPdo();
            $elapsed = round((microtime(true) - $start) * 1000);
            $sqlStatus = [
                'connected' => true,
                'ping_ms'   => $elapsed,
                'message'   => 'Connected',
            ];
        } catch (\Exception $e) {
            $sqlStatus = [
                'connected' => false,
                'ping_ms'   => 0,
                'message'   => $e->getMessage(),
            ];
        }

        // Test Acumatica
        $acumaticaStatus = $odataService->testConnection();

        return response()->json([
            'sql_server' => $sqlStatus,
            'acumatica'  => $acumaticaStatus,
        ]);
    }
}
