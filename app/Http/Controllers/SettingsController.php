<?php

namespace App\Http\Controllers;

use App\Services\EntityConfigService;
use App\Services\ConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    protected ConnectionService $connectionService;

    public function __construct(ConnectionService $connectionService)
    {
        $this->connectionService = $connectionService;
    }

    public function index(EntityConfigService $entityConfig)
    {
        $entities = $entityConfig->getAll();
        $connections = $this->connectionService->getAll();
        return view('settings.index', [
            'entities'    => $entities,
            'connections' => $connections,
            'activeTab'   => 'settings',
        ]);
    }

    public function getConnections(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'connections' => $this->connectionService->getAll(),
        ]);
    }

    public function storeConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key'         => 'required|string|max:100|regex:/^[A-Za-z0-9_]*$/',
            'name'        => 'required|string|max:255',
            'type'        => 'required|in:odata,rest_api,mysql,sqlsrv,firebird',
            'credentials' => 'required|array',
        ]);

        if ($this->connectionService->exists($validated['key'])) {
            return response()->json(['success' => false, 'message' => 'Connection key already exists.'], 422);
        }

        $this->connectionService->save($validated['key'], $validated);

        return response()->json([
            'success' => true,
            'message' => 'Connection created successfully.',
            'connection' => $this->connectionService->get($validated['key']),
        ]);
    }

    public function updateConnection(Request $request, string $key): JsonResponse
    {
        if (!$this->connectionService->exists($key)) {
            return response()->json(['success' => false, 'message' => 'Connection not found.'], 404);
        }

        $validated = $request->validate([
            'key'         => 'required|string|max:100|regex:/^[A-Za-z0-9_]*$/',
            'name'        => 'required|string|max:255',
            'type'        => 'required|in:odata,rest_api,mysql,sqlsrv,firebird',
            'credentials' => 'required|array',
        ]);

        $newKey = $validated['key'];
        if ($newKey !== $key && $this->connectionService->exists($newKey)) {
            return response()->json(['success' => false, 'message' => 'Connection key already exists.'], 422);
        }

        $this->connectionService->update($key, $newKey, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Connection updated successfully.',
            'connection' => $this->connectionService->get($newKey),
        ]);
    }

    public function destroyConnection(string $key): JsonResponse
    {
        if (!$this->connectionService->delete($key)) {
            return response()->json(['success' => false, 'message' => 'Connection not found.'], 404);
        }
        return response()->json(['success' => true, 'message' => 'Connection deleted successfully.']);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $credentials = $request->input('credentials', []);
        $key = $request->input('key');

        if (!$type && $key) {
            $conn = $this->connectionService->get($key);
            if (!$conn) {
                return response()->json(['success' => false, 'message' => 'Connection not found.'], 404);
            }
            $type = $conn['type'];
            $credentials = $conn['credentials'] ?? [];
        }

        if (in_array($type, ['mysql', 'sqlsrv', 'firebird'])) {
            try {
                $start = microtime(true);

                if ($type === 'firebird') {
                    $dsn = "firebird:dbname={$credentials['host']}/{$credentials['port']}:{$credentials['database']}";
                    $pdo = new \PDO($dsn, $credentials['username'] ?? '', $credentials['password'] ?? '');
                } else {
                    $connectionName = 'test_connection_' . time();
                    $mergedConfig = array_merge(
                        is_array(config("database.connections.{$type}")) ? config("database.connections.{$type}") : [],
                        [
                            'driver'   => $type,
                            'host'     => $credentials['host'] ?? '',
                            'port'     => $credentials['port'] ?? '',
                            'database' => $credentials['database'] ?? '',
                            'username' => $credentials['username'] ?? '',
                            'password' => $credentials['password'] ?? '',
                        ]
                    );
                    
                    if ($type === 'sqlsrv') {
                        $mergedConfig['encrypt'] = $mergedConfig['encrypt'] ?? 'no';
                        $mergedConfig['trust_server_certificate'] = $mergedConfig['trust_server_certificate'] ?? 'true';
                    }
                    
                    \Illuminate\Support\Facades\Log::info("Testing DB Connection", ['config' => $mergedConfig]);

                    config([
                        "database.connections.{$connectionName}" => $mergedConfig,
                    ]);
                    
                    try {
                        DB::connection($connectionName)->getPdo();
                    } catch (\Throwable $e) {
                    if ($type === 'firebird') throw $e;
                    
                    $isUnknownDb = false;
                    $msg = $e->getMessage();
                    if ($type === 'mysql' && str_contains($msg, 'Unknown database')) {
                        $isUnknownDb = true;
                    } elseif ($type === 'sqlsrv' && (str_contains($msg, 'Cannot open database') || str_contains($msg, 'Login failed'))) {
                        // Sometimes SQL Server throws 'Login failed' if default DB is invalid, or 'Cannot open database'
                        $isUnknownDb = true;
                    }

                    if ($isUnknownDb && !empty($credentials['database'])) {
                        // Try to create the database
                        $tempConfig = $mergedConfig;
                        $tempConfig['database'] = $type === 'sqlsrv' ? 'master' : '';
                        $tempConnName = $connectionName . '_temp';
                        config(["database.connections.{$tempConnName}" => $tempConfig]);
                        
                        try {
                            $pdo = DB::connection($tempConnName)->getPdo();
                            $dbName = $credentials['database'];
                            if ($type === 'mysql') {
                                $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '``', $dbName) . "`");
                            } elseif ($type === 'sqlsrv') {
                                $dbNameEscaped = str_replace("'", "''", $dbName);
                                $pdo->exec("IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = N'{$dbNameEscaped}') BEGIN CREATE DATABASE [{$dbName}] END");
                            }
                            
                            // Retry connection
                            DB::purge($connectionName);
                            DB::connection($connectionName)->getPdo();
                        } catch (\Throwable $createEx) {
                            // If creation fails, throw original exception or creation exception
                            throw $e;
                        }
                    } else {
                        throw $e;
                    }
                }
                }
                

                $elapsed = round((microtime(true) - $start) * 1000);

                if ($key && $conn = \App\Models\Connection::where('key', $key)->first()) {
                    $conn->update(['is_connected' => true]);
                }

                return response()->json([
                    'success' => true,
                    'message' => "Connected successfully ({$elapsed}ms)",
                    'ping_ms' => $elapsed,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("DB Connection Test Failed", ['type' => $type, 'error' => $e->getMessage()]);
                
                if ($key && $conn = \App\Models\Connection::where('key', $key)->first()) {
                    $conn->update(['is_connected' => false]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Connection failed: ' . $e->getMessage(),
                ], 422);
            }
        }

        if ($type === 'odata') {
            try {
                // We'll temporarily override the acumatica config to use the AcumaticaODataService
                config([
                    'acumatica.url'      => $credentials['url'] ?? '',
                    'acumatica.tenant'   => $credentials['tenant'] ?? '',
                    'acumatica.username' => $credentials['username'] ?? '',
                    'acumatica.password' => $credentials['password'] ?? '',
                    'acumatica.odata_path' => $credentials['odata_path'] ?? 'odatav4',
                ]);

                $odataService = app(\App\Services\AcumaticaODataService::class);
                $result = $odataService->testODataConnection();

                if ($result['connected']) {
                    if ($key && $conn = \App\Models\Connection::where('key', $key)->first()) {
                        $conn->update(['is_connected' => true]);
                    }
                    return response()->json([
                        'success' => true,
                        'message' => "OData connected successfully ({$result['ping_ms']}ms)",
                        'ping_ms' => $result['ping_ms'],
                    ]);
                } else {
                    if ($key && $conn = \App\Models\Connection::where('key', $key)->first()) {
                        $conn->update(['is_connected' => false]);
                    }
                    return response()->json([
                        'success' => false,
                        'message' => 'OData connection failed: ' . $result['message'],
                    ], 422);
                }
            } catch (\Throwable $e) {
                if ($key && $conn = \App\Models\Connection::where('key', $key)->first()) {
                    $conn->update(['is_connected' => false]);
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Connection failed: ' . $e->getMessage(),
                ], 422);
            }
        }

        return response()->json(['success' => false, 'message' => 'Unsupported connection type or test failed.'], 400);
    }

    public function disconnectConnection(string $key): JsonResponse
    {
        $conn = \App\Models\Connection::where('key', $key)->first();
        if ($conn) {
            $conn->update(['is_connected' => false]);
            return response()->json(['success' => true, 'message' => 'Disconnected successfully.']);
        }
        
        return response()->json(['success' => false, 'message' => 'Connection not found in database.'], 404);
    }

    public function fetchDatabases(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $credentials = $request->input('credentials', []);

        if (!in_array($type, ['mysql', 'sqlsrv'])) {
            return response()->json(['success' => false, 'message' => 'Unsupported type for database listing'], 400);
        }

        try {
            $connName = 'temp_databases_' . time();
            $mergedConfig = array_merge(
                is_array(config("database.connections.{$type}")) ? config("database.connections.{$type}") : [],
                [
                    'driver'   => $type,
                    'host'     => $credentials['host'] ?? '',
                    'port'     => $credentials['port'] ?? '',
                    'database' => $type === 'sqlsrv' ? 'master' : '',
                    'username' => $credentials['username'] ?? '',
                    'password' => $credentials['password'] ?? '',
                ]
            );
            if ($type === 'sqlsrv') {
                $mergedConfig['encrypt'] = $mergedConfig['encrypt'] ?? 'no';
                $mergedConfig['trust_server_certificate'] = $mergedConfig['trust_server_certificate'] ?? 'true';
            }
            config(["database.connections.{$connName}" => $mergedConfig]);

            $pdo = \Illuminate\Support\Facades\DB::connection($connName)->getPdo();
            $databases = [];

            if ($type === 'mysql') {
                $stmt = $pdo->query("SHOW DATABASES");
                while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                    $databases[] = $row[0];
                }
            } elseif ($type === 'sqlsrv') {
                $stmt = $pdo->query("SELECT name FROM sys.databases WHERE state_desc = 'ONLINE'");
                while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                    $databases[] = $row[0];
                }
            }

            return response()->json([
                'success' => true,
                'databases' => $databases
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch databases: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getConnectionObjects(string $key, \App\Services\AcumaticaODataService $odataService): JsonResponse
    {
        try {
            $type = '';
            $credentials = [];

            $conn = $this->connectionService->get($key);
            if (!$conn) {
                return response()->json(['success' => false, 'message' => 'Connection not found.'], 404);
            }
            $type = $conn['type'];
            $credentials = $conn['credentials'] ?? [];

            $objects = [];

            if ($type === 'odata') {
                $odataService->setConnectionConfig(['credentials' => $credentials]);
                $objects = $odataService->getODataEndpoints();
            } elseif (in_array($type, ['mysql', 'sqlsrv', 'firebird'])) {
                if ($type === 'firebird') {
                    $dsn = "firebird:dbname={$credentials['host']}/{$credentials['port']}:{$credentials['database']}";
                    $pdo = new \PDO($dsn, $credentials['username'] ?? '', $credentials['password'] ?? '');
                    
                    $stmt = $pdo->query("SELECT RDB\$RELATION_NAME FROM RDB\$RELATIONS WHERE RDB\$VIEW_BLR IS NULL AND (RDB\$SYSTEM_FLAG IS NULL OR RDB\$SYSTEM_FLAG = 0)");
                    while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                        $objects[] = trim($row[0]);
                    }
                } else {
                    // If it's a dynamic connection, we configure it
                    $connName = 'temp_objects_' . time();
                    $mergedConfig = array_merge(
                        is_array(config("database.connections.{$type}")) ? config("database.connections.{$type}") : [],
                        [
                            'driver'   => $type,
                            'host'     => $credentials['host'] ?? '',
                            'port'     => $credentials['port'] ?? '',
                            'database' => $credentials['database'] ?? '',
                            'username' => $credentials['username'] ?? '',
                            'password' => $credentials['password'] ?? '',
                        ]
                    );
                    if ($type === 'sqlsrv') {
                        $mergedConfig['encrypt'] = $mergedConfig['encrypt'] ?? 'no';
                        $mergedConfig['trust_server_certificate'] = $mergedConfig['trust_server_certificate'] ?? 'true';
                    }
                    config(["database.connections.{$connName}" => $mergedConfig]);

                    $pdo = \Illuminate\Support\Facades\DB::connection($connName)->getPdo();
                    if ($type === 'mysql') {
                        $stmt = $pdo->query("SHOW TABLES");
                        while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                            $objects[] = $row[0];
                        }
                    } elseif ($type === 'sqlsrv') {
                        $stmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' OR TABLE_TYPE = 'VIEW'");
                        while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                            $objects[] = $row[0];
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'objects' => $objects
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch objects: ' . $e->getMessage()
            ], 500);
        }
    }
}
