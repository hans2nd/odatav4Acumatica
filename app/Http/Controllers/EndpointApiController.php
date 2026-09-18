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
    protected \App\Services\Connections\ConnectionManager $connectionManager;

    public function __construct(ConnectionService $connectionService, \App\Services\Connections\ConnectionManager $connectionManager)
    {
        $this->connectionService = $connectionService;
        $this->connectionManager = $connectionManager;
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
            if (in_array($conn['type'] ?? '', \App\Services\Connections\ConnectionProviderFactory::getDatabaseTypes()) && ($conn['is_active'] ?? true) && !empty($conn['is_connected'])) {
                $dbConnections[] = [
                    'key'  => $key,
                    'name' => $conn['name'] ?? $key,
                    'type' => $conn['type'],
                ];
                try {
                    $tree = $this->connectionManager->discoverMetadata($key);
                    $tables = [];
                    $systemTables = [
                        'migrations', 'users', 'password_reset_tokens', 'personal_access_tokens',
                        'failed_jobs', 'jobs', 'api_endpoints', 'connections', 
                        'sync_jobs', 'sync_logs', 'sync_metadata_definitions', 
                        'sync_metadata_fields', 'sync_metadata_history', 
                        'sync_metadata_refresh_history', 'permissions', 'roles', 
                        'model_has_permissions', 'model_has_roles', 'role_has_permissions',
                        'cache', 'cache_locks', 'sessions'
                    ];
                    
                    foreach ($tree->nodes as $node) {
                        if (!in_array(strtolower($node->name), $systemTables)) {
                            $tables[] = $node->name;
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


}
