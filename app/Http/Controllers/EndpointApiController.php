<?php

namespace App\Http\Controllers;

use App\Models\ApiEndpoint;
use App\Services\ConnectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EndpointApiController extends Controller
{
    protected ConnectionService $connectionService;

    public function __construct(ConnectionService $connectionService)
    {
        $this->connectionService = $connectionService;
    }

    public function index()
    {
        $endpoints = ApiEndpoint::latest()->get();
        return view('reports.endpoint-api', compact('endpoints'));
    }

    public function create()
    {
        extract($this->getConnectionsAndTables());
        return view('reports.endpoint-api-create', compact('dbConnections', 'allTables'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'connection_key' => 'required|string',
            'table_name' => 'required|string',
            'endpoint_slug' => 'required|string|unique:api_endpoints,endpoint_slug|regex:/^[a-zA-Z0-9\-_]+$/',
            'columns' => 'nullable|array',
            'filters' => 'nullable|string',
        ]);

        $data = $request->only(['name', 'connection_key', 'table_name', 'endpoint_slug', 'columns']);
        if ($request->filled('filters')) {
            $data['filters'] = json_decode($request->filters, true);
        }

        ApiEndpoint::create($data);
        app(\App\Services\PermissionGeneratorService::class)->generateApiEndpoints();

        return redirect()->route('endpoint.api.index')->with('success', 'Endpoint API created successfully.');
    }

    public function edit($id)
    {
        $endpoint = ApiEndpoint::findOrFail($id);
        extract($this->getConnectionsAndTables());
        
        return view('reports.endpoint-api-edit', compact('endpoint', 'dbConnections', 'allTables'));
    }

    public function update(Request $request, $id)
    {
        $endpoint = ApiEndpoint::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'connection_key' => 'required|string',
            'table_name' => 'required|string',
            'endpoint_slug' => 'required|string|regex:/^[a-zA-Z0-9\-_]+$/|unique:api_endpoints,endpoint_slug,' . $id,
            'columns' => 'nullable|array',
            'filters' => 'nullable|string',
        ]);

        $data = $request->only(['name', 'connection_key', 'table_name', 'endpoint_slug', 'columns']);
        if ($request->filled('filters')) {
            $data['filters'] = json_decode($request->filters, true);
        } else {
            $data['filters'] = []; // Clear filters if empty
        }

        $endpoint->update($data);
        app(\App\Services\PermissionGeneratorService::class)->generateApiEndpoints();

        return redirect()->route('endpoint.api.index')->with('success', 'Endpoint API updated successfully.');
    }

    public function destroy($id)
    {
        $endpoint = ApiEndpoint::findOrFail($id);
        $endpoint->delete();
        app(\App\Services\PermissionGeneratorService::class)->generateApiEndpoints();
        return redirect()->route('endpoint.api.index')->with('success', 'Endpoint API deleted successfully.');
    }

    public function toggle($id)
    {
        $endpoint = ApiEndpoint::findOrFail($id);
        $endpoint->update(['is_active' => !$endpoint->is_active]);
        // No need to sync permissions for toggling, as the endpoint still exists.
        return redirect()->route('endpoint.api.index')->with('success', 'Endpoint status updated.');
    }

    private function getConnectionsAndTables()
    {
        $all = $this->connectionService->getAll();
        $dbConnections = [];
        $allTables = [];

        foreach ($all as $key => $conn) {
            if (in_array($conn['type'] ?? '', ['mysql', 'sqlsrv', 'firebird']) && ($conn['is_active'] ?? true) && !empty($conn['is_connected'])) {
                $dbConnections[] = [
                    'key'  => $key,
                    'name' => $conn['name'] ?? $key,
                    'type' => $conn['type'],
                ];
                
                try {
                    $connName = $this->resolveConnection($key);
                    $driver = DB::connection($connName)->getDriverName();
                    
                    $tables = [];
                    if ($driver === 'mysql') {
                        $results = DB::connection($connName)->select("SHOW TABLES");
                        foreach ($results as $row) {
                            $tables[] = array_values((array) $row)[0];
                        }
                    } elseif ($driver === 'sqlsrv') {
                        $results = DB::connection($connName)->select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");
                        foreach ($results as $row) {
                            $tables[] = $row->TABLE_NAME;
                        }
                    } elseif ($driver === 'firebird') {
                        $results = DB::connection($connName)->select("SELECT RDB\$RELATION_NAME FROM RDB\$RELATIONS WHERE RDB\$VIEW_BLR IS NULL AND (RDB\$SYSTEM_FLAG IS NULL OR RDB\$SYSTEM_FLAG = 0)");
                        foreach ($results as $row) {
                            $tables[] = trim(array_values((array) $row)[0]);
                        }
                    }
                    sort($tables);
                    $allTables[$key] = $tables;
                } catch (\Exception $e) {
                    $allTables[$key] = [];
                }
            }
        }
        
        return ['dbConnections' => $dbConnections, 'allTables' => $allTables];
    }

    protected function resolveConnection($connectionKey)
    {
        $conn = $this->connectionService->get($connectionKey);
        if (!$conn) {
            throw new \Exception("Connection not found: {$connectionKey}");
        }

        $type = $conn['type'];
        $creds = $conn['credentials'] ?? [];
        $laravelConnName = 'dyn_' . $connectionKey;
        
        if (!config()->has("database.connections.{$laravelConnName}")) {
            $mergedConfig = array_merge(
                is_array(config("database.connections.{$type}")) ? config("database.connections.{$type}") : [],
                [
                    'driver'   => $type,
                    'host'     => $creds['host'] ?? '',
                    'port'     => $creds['port'] ?? '',
                    'database' => $creds['database'] ?? '',
                    'username' => $creds['username'] ?? '',
                    'password' => $creds['password'] ?? '',
                ]
            );

            if ($type === 'sqlsrv') {
                $mergedConfig['encrypt'] = $mergedConfig['encrypt'] ?? 'no';
                $mergedConfig['trust_server_certificate'] = $mergedConfig['trust_server_certificate'] ?? 'true';
            }

            config(["database.connections.{$laravelConnName}" => $mergedConfig]);
        }

        return $laravelConnName;
    }
}