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
    protected \App\Services\Connections\ConnectionManager $connectionManager;

    public function __construct(ConnectionService $connectionService, \App\Services\Connections\ConnectionManager $connectionManager)
    {
        $this->connectionService = $connectionService;
        $this->connectionManager = $connectionManager;
    }

    public function index(EntityConfigService $entityConfig)
    {
        $entities = $entityConfig->getAll();
        $connections = $this->connectionService->getAll();
        
        $schemas = [];
        $providers = config('providers.registry', []);
        $activeProviders = array_filter($providers, fn($p) => !empty($p['enabled']));
        foreach (array_keys($activeProviders) as $type) {
            $schemas[$type] = $this->connectionManager->getConfigurationSchema($type);
        }

        return view('settings.index', [
            'entities'    => $entities,
            'connections' => $connections,
            'schemas'     => $schemas,
            'providers'   => $activeProviders,
            'activeTab'   => 'settings',
        ]);
    }

    public function systemUtilities(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized. Superadmin only.');
        }

        return view('settings.system-utilities');
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
        $validTypes = array_merge(
            array_keys(array_filter(config('providers.registry', []), fn($p) => !empty($p['enabled']))),
            array_keys(config('providers.aliases', []))
        );

        $validated = $request->validate([
            'key'         => 'required|string|max:100|regex:/^[A-Za-z0-9_]*$/',
            'name'        => 'required|string|max:255',
            'type'        => ['required', \Illuminate\Validation\Rule::in($validTypes)],
            'credentials' => 'nullable|array',
        ]);

        // Resolve alias if present
        $aliases = config('providers.aliases', []);
        if (isset($aliases[$validated['type']])) {
            $validated['type'] = $aliases[$validated['type']];
        }

        if ($this->connectionService->exists($validated['key'])) {
            return response()->json(['success' => false, 'message' => 'Connection key already exists.'], 422);
        }

        if ($validated['type'] === 'local') {
            $validated['is_connected'] = true;
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

        $validTypes = array_merge(
            array_keys(array_filter(config('providers.registry', []), fn($p) => !empty($p['enabled']))),
            array_keys(config('providers.aliases', []))
        );

        $validated = $request->validate([
            'key'         => 'required|string|max:100|regex:/^[A-Za-z0-9_]*$/',
            'name'        => 'required|string|max:255',
            'type'        => ['required', \Illuminate\Validation\Rule::in($validTypes)],
            'credentials' => 'nullable|array',
        ]);

        // Resolve alias if present
        $aliases = config('providers.aliases', []);
        if (isset($aliases[$validated['type']])) {
            $validated['type'] = $aliases[$validated['type']];
        }

        $newKey = $validated['key'];
        if ($newKey !== $key && $this->connectionService->exists($newKey)) {
            return response()->json(['success' => false, 'message' => 'Connection key already exists.'], 422);
        }

        if ($validated['type'] === 'local') {
            $validated['is_connected'] = true;
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

    public function batchDestroyConnections(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keys'   => 'required|array|min:1',
            'keys.*' => 'string|max:100',
        ]);

        $keys = array_values(array_unique($validated['keys']));

        try {
            if (!$this->connectionService->batchDelete($keys)) {
                return response()->json([
                    'success' => false,
                    'message' => "Failed to delete the selected connection(s).",
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => count($keys) . " connection(s) deleted successfully.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => "Error deleting connections: " . $e->getMessage(),
            ], 500);
        }
    }

    public function setAnalyticsSource(string $key): JsonResponse
    {
        $conn = \App\Models\Connection::where('key', $key)->first();
        if (!$conn) {
            return response()->json(['success' => false, 'message' => 'Connection not found.'], 404);
        }

        try {
            $this->connectionService->setAnalyticsSource($conn->id);
            return response()->json(['success' => true, 'message' => 'Analytics source updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update analytics source: ' . $e->getMessage()], 500);
        }
    }

    public function getProviderSchema(string $type): JsonResponse
    {
        try {
            $schema = $this->connectionManager->getConfigurationSchema($type);
            return response()->json(['success' => true, 'schema' => $schema]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function testConnection(Request $request): JsonResponse
    {
        return $this->handleConnectionCheck($request, false);
    }

    public function runDiagnostics(Request $request): JsonResponse
    {
        return $this->handleConnectionCheck($request, true);
    }

    private function handleConnectionCheck(Request $request, bool $fullDiagnostics): JsonResponse
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

        try {
            if ($fullDiagnostics) {
                $health = $this->connectionManager->runDiagnostics($type, $credentials, $key);
            } else {
                $health = $this->connectionManager->testConnection($type, $credentials, $key);
            }
            
            $isHealthy = $health->status === \App\DTO\Connections\ConnectionHealthStatus::HEALTHY || 
                         $health->status === \App\DTO\Connections\ConnectionHealthStatus::WARNING;

            if ($key && $conn = \App\Models\Connection::where('key', $key)->first()) {
                $conn->update(['is_connected' => $isHealthy]);
            }

            return response()->json([
                'success' => $isHealthy,
                'message' => $isHealthy ? ($health->message . ($health->pingMs !== null ? " ({$health->pingMs}ms)" : '')) : ('Connection failed: ' . $health->message),
                'ping_ms' => $health->pingMs,
                'status'  => $health->status->value,
                'checks'  => $health->checks ?? [],
                'metrics' => $health->metrics ?? [],
                'summary' => $health->summary ?? [],
                'error'   => $health->error?->toArray(),
            ], $isHealthy ? 200 : 422);

        } catch (\Throwable $e) {
            if ($key && $conn = \App\Models\Connection::where('key', $key)->first()) {
                $conn->update(['is_connected' => false]);
            }
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
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
        // For databases, we can use the discovery mechanisms, but let's just return success for now 
        // to simplify the UI dropdown which requested raw database names. 
        // We will adapt the UI to rely purely on schema fields.
        return response()->json(['success' => true, 'databases' => []]);
    }

    public function getConnectionObjects(string $key): JsonResponse
    {
        try {
            $forceRefresh = request()->query('force') === 'true';
            $tree = $this->connectionManager->discoverMetadata($key, $forceRefresh);
            
            $objects = [];
            foreach ($tree->nodes as $node) {
                $objects[] = $node->name;
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

    public function getConnectionObjectColumns(string $key, string $objectName): JsonResponse
    {
        try {
            // Note: objectName may contain slashes for SharePoint, we need to handle it properly if passed via query or path.
            // Assuming it's passed safely in the URL path.
            $node = $this->connectionManager->discoverStructure($key, urldecode($objectName));
            
            $columns = [];
            foreach ($node->properties as $prop) {
                $propName = is_array($prop) ? ($prop['name'] ?? $prop['Field'] ?? '') : (string)$prop;
                $propType = is_array($prop) ? ($prop['type'] ?? $prop['Type'] ?? '') : '';
                if ($propName) {
                    $columns[] = [
                        'name' => $propName,
                        'type' => $propType,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'columns' => $columns
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch columns: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getConnectionObjectSheets(string $key, string $objectName): JsonResponse
    {
        try {
            $provider = $this->connectionManager->getProvider($key);
            if (method_exists($provider, 'listExcelSheets')) {
                $connection = \App\Models\Connection::where('key', $key)->firstOrFail();
                $sheets = $provider->listExcelSheets($connection->credentials ?? [], urldecode($objectName));
                return response()->json(['success' => true, 'sheets' => $sheets]);
            }
            return response()->json(['success' => true, 'sheets' => []]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sheets: ' . $e->getMessage()
            ], 500);
        }
    }
    public function fetchSharePointSites(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'tenant_id' => 'required|string',
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
            'hostname' => 'required|string',
        ]);

        try {
            $client = new \App\Services\Connections\Providers\Adapters\SharePointGraphClient($credentials);
            $sites = $client->searchSites();

            return response()->json([
                'success' => true,
                'sites' => $sites
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load sites: ' . $e->getMessage()
            ], 500);
        }
    }

    public function downloadConnectionObject(string $key, string $objectName)
    {
        try {
            $provider = $this->connectionManager->getProvider($key);
            if (method_exists($provider, 'downloadObject')) {
                $connection = \App\Models\Connection::where('key', $key)->firstOrFail();
                $tempPath = $provider->downloadObject($connection->credentials ?? [], urldecode($objectName));

                if (!$tempPath || !file_exists($tempPath)) {
                    return response()->json(['success' => false, 'message' => 'Failed to download file from provider.'], 500);
                }

                $fileName = basename($tempPath);
                // Strip the temp prefix to get the original name
                $fileName = preg_replace('/^sp_[a-zA-Z0-9]+_/', '', $fileName);

                return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
            }
            return response()->json(['success' => false, 'message' => 'Download not supported for this provider.'], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download object: ' . $e->getMessage()
            ], 500);
        }
    }
}
