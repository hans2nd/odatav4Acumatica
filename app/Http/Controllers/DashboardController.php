<?php

namespace App\Http\Controllers;

use App\Models\SyncLog;
use App\Services\AcumaticaODataService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $activeConnections = \App\Models\Connection::where('is_connected', true)
            ->get()
            ->keyBy('key')
            ->toArray();

        $hasLogsTable = \Illuminate\Support\Facades\Schema::hasTable('sync_logs');
        $recentLogs = $hasLogsTable ? SyncLog::orderByDesc('id')
            ->limit(10)
            ->get() : collect();

        return view('dashboard', [
            'activeConnections'  => $activeConnections,
            'recentLogs'         => $recentLogs,
            'activeTab'          => 'overview',
        ]);
    }

    public function connectionStatus(): JsonResponse
    {
        $all = \App\Models\Connection::where('is_connected', true)->get();
        $statuses = [];

        foreach ($all as $connModel) {
            $key = $connModel->key;
            $conn = $connModel->toArray();

            $status = [
                'connected' => false, 
                'ping_ms' => 0, 
                'message' => 'Not tested', 
                'type' => $conn['type'], 
                'name' => $conn['name'] ?? $key
            ];
            
            $start = microtime(true);
            try {
                if (in_array($conn['type'], ['mysql', 'sqlsrv', 'firebird', 'local_env'])) {
                    if (config()->has("database.connections.{$key}")) {
                        DB::connection($key)->getPdo();
                        $elapsed = round((microtime(true) - $start) * 1000);
                        $status['connected'] = true;
                        $status['ping_ms'] = $elapsed;
                        $status['message'] = 'Connected';
                    } else {
                        $status['message'] = 'Driver not configured by provider';
                    }
                } elseif ($conn['type'] === 'odata' || $conn['type'] === 'rest_api') {
                    $odataService = app(\App\Services\AcumaticaODataService::class);
                    $odataService->setConnectionConfig($conn);
                    $testResult = $odataService->testConnection();
                    $status['connected'] = $testResult['connected'] ?? false;
                    $status['ping_ms'] = $testResult['ping_ms'] ?? 0;
                    $status['message'] = $testResult['message'] ?? 'Tested';
                }
            } catch (\Exception $e) {
                $status['message'] = $e->getMessage();
            }
            
            $statuses[$key] = $status;
        }

        return response()->json($statuses);
    }
}
