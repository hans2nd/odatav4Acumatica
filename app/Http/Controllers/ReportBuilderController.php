<?php

namespace App\Http\Controllers;

use App\Services\ConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReportBuilderController extends Controller
{
    protected ConnectionService $connectionService;
    protected \App\Services\Connections\ConnectionManager $connectionManager;

    public function __construct(ConnectionService $connectionService, \App\Services\Connections\ConnectionManager $connectionManager)
    {
        $this->connectionService = $connectionService;
        $this->connectionManager = $connectionManager;
    }

    /**
     * Show the Report Builder page.
     */
    public function index()
    {
        $all = $this->connectionService->getAll();
        $dbConnections = [];
        $allTables = [];
        $user = auth()->user();

        foreach ($all as $key => $conn) {
            // Only include active and connected database-type connections
            if (in_array($conn['type'] ?? '', \App\Services\Connections\ConnectionProviderFactory::getDatabaseTypes()) && ($conn['is_active'] ?? true) && !empty($conn['is_connected'])) {
                
                // Check permissions
                if ($user && !$user->isSuperAdmin() && !$user->hasPermission("connection.{$key}.access")) {
                    continue;
                }

                $dbConnections[] = [
                    'key'  => $key,
                    'name' => $conn['name'] ?? $key,
                    'type' => $conn['type'],
                ];
                
                // Preload tables for this connection using unified filtering logic
                try {
                    $allTables[$key] = $this->resolveTablesForConnection($key, $user, false);
                } catch (\Throwable $e) {
                    $allTables[$key] = []; // On error, just empty array
                }
            }
        }

        $templates = \App\Models\ReportTemplate::with('roles')
            ->where('user_id', $user ? $user->id : null)
            ->orWhereHas('roles', function($q) use ($user) {
                if ($user) {
                    $q->whereIn('roles.id', $user->roles->pluck('id'));
                }
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return view('reports.report-builder', [
            'activeTab'   => 'report.builder',
            'connections' => $dbConnections,
            'allTables'   => $allTables,
            'templates'   => $templates,
        ]);
    }

    /**
     * Resolve and filter available tables for a database connection.
     */
    protected function resolveTablesForConnection(string $connKey, $user = null, bool $forceRefresh = false): array
    {
        $tree = $this->connectionManager->discoverMetadata($connKey, $forceRefresh);
        $tables = [];

        $systemTables = [
            'migrations', 'users', 'password_reset_tokens', 'personal_access_tokens',
            'failed_jobs', 'jobs', 'api_endpoints', 'connections',
            'sync_jobs', 'sync_logs', 'sync_metadata_definitions',
            'sync_metadata_fields', 'sync_metadata_history',
            'sync_metadata_refresh_history', 'permissions', 'roles',
            'model_has_permissions', 'model_has_roles', 'role_has_permissions',
            'cache', 'cache_locks', 'sessions', 'rbac_audit_logs', 'report_templates',
            'report_template_role', 'sync_entities', 'sync_field_mappings',
        ];

        // Collect all configured tables for this connection from Entity Configuration
        $entities = app(\App\Services\EntityConfigService::class)->getAll();
        $configuredTables = [];
        foreach ($entities as $entity) {
            if (($entity['destination_connection'] ?? 'database_default') === $connKey && !empty($entity['table'])) {
                $tbl = strtolower(trim($entity['table']));
                $configuredTables[] = $tbl;
                $configuredTables[] = preg_replace('/^dbo\./i', '', $tbl);
            }
            if (($entity['source_connection'] ?? 'acumatica_default') === $connKey && !empty($entity['odata_entity'])) {
                $srcTbl = strtolower(trim($entity['odata_entity']));
                $configuredTables[] = $srcTbl;
                $configuredTables[] = preg_replace('/^dbo\./i', '', $srcTbl);
            }
        }
        $configuredTables = array_values(array_unique(array_filter($configuredTables)));
        $hasConfiguredEntities = !empty($configuredTables);

        foreach ($tree->nodes as $node) {
            $nodeNameLower = strtolower(trim($node->name));
            $nodeNameStripped = preg_replace('/^dbo\./i', '', $nodeNameLower);

            if (in_array($nodeNameLower, $systemTables, true) || in_array($nodeNameStripped, $systemTables, true)) {
                continue;
            }

            // If this connection has configured entities, filter by them; otherwise fallback to all non-system tables
            if ($hasConfiguredEntities && !in_array($nodeNameLower, $configuredTables, true) && !in_array($nodeNameStripped, $configuredTables, true)) {
                continue;
            }

            if ($user && !$user->isSuperAdmin() && !$user->hasPermission("connection.{$connKey}.table.{$node->name}.access")) {
                continue;
            }

            $tables[] = $node->name;
        }

        sort($tables);

        return $tables;
    }

    /**
     * List all database-type connections (exclude OData).
     */
    public function connections(): JsonResponse
    {
        $all = $this->connectionService->getAll();
        $dbConnections = [];
        $user = auth()->user();

        foreach ($all as $key => $conn) {
            // Only include active and connected database-type connections
            if (in_array($conn['type'] ?? '', \App\Services\Connections\ConnectionProviderFactory::getDatabaseTypes()) && ($conn['is_active'] ?? true) && !empty($conn['is_connected'])) {
                
                // Check permissions
                if ($user && !$user->isSuperAdmin() && !$user->hasPermission("connection.{$key}.access")) {
                    continue;
                }

                try {
                    $connName = $this->connectionService->resolveConnectionName($key);
                    \Illuminate\Support\Facades\DB::connection($connName)->getPdo();

                    $dbConnections[] = [
                        'key'  => $key,
                        'name' => $conn['name'] ?? $key,
                        'type' => $conn['type'],
                    ];
                } catch (\Exception $e) {
                    // Connection failed, do not include in the dropdown
                    \Illuminate\Support\Facades\Log::warning("Report Builder: Connection '{$key}' is inactive or unreachable.");
                }
            }
        }

        return response()->json(['success' => true, 'connections' => $dbConnections]);
    }

    public function checkProgress(Request $request)
    {
        $downloadId = $request->query('download_id');
        if (!$downloadId) {
            return response()->json(['success' => false]);
        }
        $data = \Illuminate\Support\Facades\Cache::get("report_dl_{$downloadId}");
        if (!$data) {
            return response()->json(['success' => true, 'done' => true]);
        }
        return response()->json([
            'success' => true,
            'done' => false,
            'total' => $data['total'],
            'current' => $data['current'],
            'percentage' => $data['total'] > 0 ? round(($data['current'] / $data['total']) * 100) : 0
        ]);
    }

    /**
     * List tables for a given connection.
     */
    public function tables(Request $request): JsonResponse
    {
        $connKey = $request->input('connection');
        if (!$connKey) {
            return response()->json(['success' => false, 'message' => 'Connection key is required.'], 400);
        }

        $user = auth()->user();
        if ($user && !$user->isSuperAdmin() && !$user->hasPermission("connection.{$connKey}.access")) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to this connection.'], 403);
        }

        // Default refresh to true so connecting/selecting a DB always fetches up-to-date tables without artisan cache:clear
        $forceRefresh = $request->has('refresh') ? $request->boolean('refresh') : true;

        try {
            $tables = $this->resolveTablesForConnection($connKey, $user, $forceRefresh);

            return response()->json(['success' => true, 'tables' => $tables]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch tables: ' . $e->getMessage()], 500);
        }
    }

    /**
     * List columns for a given connection + table.
     */
    public function columns(Request $request, \App\Contracts\Metadata\MetadataRepositoryInterface $metadataRepository): JsonResponse
    {
        $connKey = $request->input('connection');
        $table   = $request->input('table');

        if (!$connKey || !$table) {
            return response()->json(['success' => false, 'message' => 'Connection and table are required.'], 400);
        }

        $forceRefresh = $request->boolean('refresh', false);

        try {
            $result = $metadataRepository->getTableMetadata($connKey, $table, $forceRefresh);
            if ((!$result->isSuccess || empty($result->fields)) && !$forceRefresh) {
                $result = $metadataRepository->getTableMetadata($connKey, $table, true);
            }

            if (!$result->isSuccess) {
                return response()->json(['success' => false, 'message' => 'Failed to fetch columns: ' . $result->errorMessage], 500);
            }
            
            $columns = array_column($result->fields, 'name');

            return response()->json(['success' => true, 'columns' => $columns]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch columns: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Prepare an export file and return a download token.
     */
    public function prepare(Request $request): JsonResponse
    {
        $request->validate([
            'connection' => 'required|string',
            'table'      => 'required|string',
            'columns'    => 'required|array|min:1',
            'format'     => 'required|in:csv,xlsx',
            'filters'    => 'nullable|array',
        ]);

        $connKey = $request->input('connection');
        $table   = $request->input('table');
        $columns = $request->input('columns');
        $format  = $request->input('format');
        $filters = $request->input('filters', []);
        
        $downloadId = $request->input('download_id');
        $locale = $request->input('locale', 'id-ID'); // default to ID
        $timezone = $request->input('timezone', 'Asia/Jakarta');

        try {
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', '0'); // Unlimited for streaming

            $connName = $this->connectionService->resolveConnectionName($connKey);

            // Validate columns exist
            $node = $this->connectionManager->discoverStructure($connKey, $table);
            $availableColumns = [];
            foreach ($node->properties as $prop) {
                $availableColumns[] = is_array($prop) ? ($prop['name'] ?? $prop['Field'] ?? '') : (string)$prop;
            }
            $availableColumns = array_filter($availableColumns);
            $validColumns = array_intersect($columns, $availableColumns);

            // If cached structure is stale and misses selected columns, refresh structure once
            if (count($validColumns) < count($columns)) {
                $node = $this->connectionManager->discoverStructure($connKey, $table, true);
                $availableColumns = [];
                foreach ($node->properties as $prop) {
                    $availableColumns[] = is_array($prop) ? ($prop['name'] ?? $prop['Field'] ?? '') : (string)$prop;
                }
                $availableColumns = array_filter($availableColumns);
                $validColumns = array_intersect($columns, $availableColumns);
            }

            if (empty($validColumns)) {
                return response()->json(['success' => false, 'message' => 'No valid columns selected.'], 400);
            }

            // Build query
            $query = DB::connection($connName)->table($table);

            // Apply filters
            foreach ($filters as $filter) {
                $field = $filter['field'] ?? null;
                $op    = $filter['operator'] ?? 'eq';
                $value = $filter['value'] ?? null;

                if (!$field || $value === null || $value === '' || !in_array($field, $availableColumns)) {
                    continue;
                }
                
                // Detect if the value is exactly YYYY-MM-DD (Date Picker format)
                $isDateOnly = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);

                switch ($op) {
                    case 'eq':         
                        if ($isDateOnly) { $query->whereDate($field, '=', $value); } else { $query->where($field, '=', $value); }
                        break;
                    case 'ne':         
                        if ($isDateOnly) { $query->whereDate($field, '!=', $value); } else { $query->where($field, '!=', $value); }
                        break;
                    case 'contains':   
                        $query->where($field, 'like', '%' . $value . '%'); 
                        break;
                    case 'startswith': 
                        $query->where($field, 'like', $value . '%'); 
                        break;
                    case 'endswith':   
                        $query->where($field, 'like', '%' . $value); 
                        break;
                    case 'gt':         
                        if ($isDateOnly) { $query->whereDate($field, '>', $value); } else { $query->where($field, '>', $value); }
                        break;
                    case 'ge':         
                        if ($isDateOnly) { $query->whereDate($field, '>=', $value); } else { $query->where($field, '>=', $value); }
                        break;
                    case 'lt':         
                        if ($isDateOnly) { $query->whereDate($field, '<', $value); } else { $query->where($field, '<', $value); }
                        break;
                    case 'le':         
                        if ($isDateOnly) { $query->whereDate($field, '<=', $value); } else { $query->where($field, '<=', $value); }
                        break;
                }
            }

            $baseFilename = $table . '_' . now()->format('Ymd_His');
            $downloadName = $baseFilename . ($format === 'xlsx' ? '.xlsx' : '.csv');

            // Check Report Cache for identical shared export parameters
            $reportCache = app(\App\Services\ReportCacheService::class);
            $cacheKey = $reportCache->generateCacheKey('report_builder', $connKey, $table, [
                'columns' => $validColumns,
                'filters' => $filters,
                'locale'  => $locale,
                'timezone'=> $timezone,
            ], $format);

            $cachedReport = $reportCache->getCachedReport($cacheKey, $format);
            if ($cachedReport) {
                $token = Str::random(32);
                \Illuminate\Support\Facades\Cache::put("report_download_{$token}", [
                    'path'      => $cachedReport['path'],
                    'filename'  => $downloadName,
                    'is_cached' => true,
                ], now()->addHour());

                return response()->json([
                    'success'   => true,
                    'token'     => $token,
                    'filename'  => $downloadName,
                    'size'      => $this->formatBytes($cachedReport['size']),
                    'cached'    => true,
                ]);
            }

            $countSql = $query->clone()->selectRaw('count(*) as aggregate')->toSql();
            $countBindings = $query->getBindings();
            $countResult = app(\App\Services\QueryRepositoryService::class)->executeDynamic($connKey, $countSql, $countBindings);

            // Now apply the select to the main query
            $query->select($validColumns);
            
            $firstRow = $countResult['data'][0] ?? null;
            $totalRows = 0;
            if ($firstRow) {
                // Handle object or array return
                $totalRows = (int) (is_array($firstRow) ? ($firstRow['aggregate'] ?? 0) : ($firstRow->aggregate ?? 0));
            }
            if ($downloadId) {
                \Illuminate\Support\Facades\Cache::put("report_dl_{$downloadId}", ['total' => $totalRows, 'current' => 0], 300);
            }

            $filePath = $reportCache->getCachePath($cacheKey, $format);

            $writer = \Spatie\SimpleExcel\SimpleExcelWriter::create($filePath);

            // For Excel, attempt to set sheet name (spout underlying)
            if ($format === 'xlsx') {
                try {
                    if (method_exists($writer, 'getWriter')) {
                        $spoutWriter = $writer->getWriter();
                        if (method_exists($spoutWriter, 'getCurrentSheet')) {
                            $sheet = $spoutWriter->getCurrentSheet();
                            if (method_exists($sheet, 'setName')) {
                                $sheet->setName(mb_substr($table, 0, 31));
                            }
                        }
                    }
                } catch (\Throwable $e) {}
            }

            $writer->addHeader($validColumns);

            // Create formatters based on locale
            $numFormatter = null;
            if (extension_loaded('intl')) {
                try {
                    $numFormatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
                    $numFormatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 4);
                } catch (\Throwable $e) {}
            }

            $count = 0;
            $sql = $query->toSql();
            $bindings = $query->getBindings();
            $cursor = app(\App\Services\QueryRepositoryService::class)->executeStream($connKey, $sql, $bindings);

            foreach ($cursor as $row) {
                $rowArray = [];
                foreach ($validColumns as $col) {
                    $val = $row->{$col} ?? '';
                    
                    // Format numbers with thousand separator
                    if (is_numeric($val) && strpos($col, 'ID') === false && strpos($col, 'CD') === false && strpos($col, 'Nbr') === false && strpos(strtolower($col), 'phone') === false) {
                        if ($format === 'xlsx') {
                            $val = (float)$val;
                        } else {
                            if ($numFormatter) {
                                $val = $numFormatter->format((float)$val);
                            } else {
                                $val = number_format((float)$val, 2, ',', '.'); // Fallback to ID format
                            }
                        }
                    } 
                    // Format Dates
                    elseif (is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:?\d{2})?)?$/i', trim($val))) {
                        $hasTime = preg_match('/[ T]\d{2}:\d{2}/', trim($val));
                        if ($format === 'xlsx') {
                            try {
                                 // Return formatted string instead of DateTime object to prevent Excel from showing raw serial numbers (e.g., 45600)
                                 $val = \Carbon\Carbon::parse(trim($val))->timezone($timezone)->format($hasTime ? 'Y-m-d H:i:s' : 'Y-m-d');
                            } catch (\Throwable $e) {}
                        } else {
                            try {
                                $carbon = \Carbon\Carbon::parse(trim($val))->timezone($timezone)->locale($locale);
                                $val = $carbon->translatedFormat($hasTime ? 'd M Y H:i:s' : 'd M Y');
                            } catch (\Throwable $e) {}
                        }
                    }

                    $rowArray[$col] = $val;
                }
                $writer->addRow($rowArray);
                
                $count++;
                if ($count % 500 === 0) {
                    if ($downloadId) {
                        \Illuminate\Support\Facades\Cache::put("report_dl_{$downloadId}", ['total' => $totalRows, 'current' => $count], 300);
                    }
                    flush();
                }
            }

            $writer->close();

            // Store in Report Cache for subsequent downloads
            $reportCache->storeCachedReport($cacheKey, $filePath, $downloadName, $format);

            if ($downloadId) {
                \Illuminate\Support\Facades\Cache::forget("report_dl_{$downloadId}");
            }

            $token = Str::random(32);
            \Illuminate\Support\Facades\Cache::put("report_download_{$token}", [
                'path'      => $filePath,
                'filename'  => $downloadName,
                'is_cached' => true,
            ], now()->addHour());

            return response()->json([
                'success'  => true,
                'token'    => $token,
                'filename' => $downloadName,
                'size'     => $this->formatBytes(filesize($filePath)),
            ]);
        } catch (\Throwable $e) {
            Log::error('Report Builder prepare failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Failed to prepare report: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Download a prepared report file by token.
     */
    public function download(string $token)
    {
        $info = \Illuminate\Support\Facades\Cache::get("report_download_{$token}");

        if (!$info || !file_exists($info['path'])) {
            abort(404, 'Download link has expired or file not found.');
        }

        $response = response()->download($info['path'], $info['filename']);
        if (empty($info['is_cached'])) {
            $response->deleteFileAfterSend(true);
        }
        return $response;
    }

    // ─── Private Helpers ─────────────────────────────────────────────────



    /**
     * Generate a CSV file from the query.
     */
    private function generateCsv($query, array $columns, string $filePath): void
    {
        $writer = \Spatie\SimpleExcel\SimpleExcelWriter::create($filePath)
            ->addHeader($columns);

        foreach ($query->cursor() as $row) {
            $rowArray = [];
            foreach ($columns as $col) {
                $rowArray[$col] = $row->{$col} ?? '';
            }
            $writer->addRow($rowArray);
        }
        $writer->close();
    }

    /**
     * Generate an Excel file from the query.
     */
    private function generateExcel($query, array $columns, string $filePath, string $sheetTitle): void
    {
        $writer = \Spatie\SimpleExcel\SimpleExcelWriter::create($filePath);

        // Attempt to set sheet name using underlying Spout writer
        try {
            if (method_exists($writer, 'getWriter')) {
                $spoutWriter = $writer->getWriter();
                if (method_exists($spoutWriter, 'getCurrentSheet')) {
                    $sheet = $spoutWriter->getCurrentSheet();
                    $safeTitle = mb_substr($sheetTitle, 0, 31);
                    if (method_exists($sheet, 'setName')) {
                        $sheet->setName($safeTitle);
                    }
                }
            }
        } catch (\Throwable $e) {}

        $writer->addHeader($columns);

        foreach ($query->cursor() as $row) {
            $rowArray = [];
            foreach ($columns as $col) {
                $rowArray[$col] = $row->{$col} ?? '';
            }
            $writer->addRow($rowArray);
        }
        $writer->close();
    }

    /**
     * Format bytes to human-readable string.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' bytes';
    }


}
