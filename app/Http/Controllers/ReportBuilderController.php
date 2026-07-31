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

        foreach ($all as $key => $conn) {
            // Only include active and connected database-type connections
            if (in_array($conn['type'] ?? '', \App\Services\Connections\ConnectionProviderFactory::getDatabaseTypes()) && ($conn['is_active'] ?? true) && !empty($conn['is_connected'])) {
                $dbConnections[] = [
                    'key'  => $key,
                    'name' => $conn['name'] ?? $key,
                    'type' => $conn['type'],
                ];
                
                // Preload tables for this connection
                try {
                    $tree = $this->connectionManager->discoverMetadata($key);
                    $tables = [];
                    foreach ($tree->nodes as $node) {
                        $tables[] = $node->name;
                    }
                    sort($tables);
                    $allTables[$key] = $tables;
                } catch (\Exception $e) {
                    $allTables[$key] = []; // On error, just empty array
                }
            }
        }

        return view('reports.report-builder', [
            'activeTab'   => 'report.builder',
            'connections' => $dbConnections,
            'allTables'   => $allTables,
        ]);
    }

    /**
     * List all database-type connections (exclude OData).
     */
    public function connections(): JsonResponse
    {
        $all = $this->connectionService->getAll();
        $dbConnections = [];

        foreach ($all as $key => $conn) {
            // Only include active and connected database-type connections
            if (in_array($conn['type'] ?? '', \App\Services\Connections\ConnectionProviderFactory::getDatabaseTypes()) && ($conn['is_active'] ?? true) && !empty($conn['is_connected'])) {
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

    /**
     * List tables for a given connection.
     */
    public function tables(Request $request): JsonResponse
    {
        $connKey = $request->input('connection');
        if (!$connKey) {
            return response()->json(['success' => false, 'message' => 'Connection key is required.'], 400);
        }

        try {
            $tree = $this->connectionManager->discoverMetadata($connKey);
            $tables = [];
            foreach ($tree->nodes as $node) {
                $tables[] = $node->name;
            }
            sort($tables);

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

        try {
            $result = $metadataRepository->getTableMetadata($connKey, $table);
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

        try {
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', '300'); // 5 minutes

            $connName = $this->connectionService->resolveConnectionName($connKey);

            // Validate columns exist
            $node = $this->connectionManager->discoverStructure($connKey, $table);
            $availableColumns = [];
            foreach ($node->properties as $prop) {
                $availableColumns[] = is_array($prop) ? ($prop['name'] ?? $prop['Field'] ?? '') : (string)$prop;
            }
            $availableColumns = array_filter($availableColumns);
            $validColumns = array_intersect($columns, $availableColumns);

            if (empty($validColumns)) {
                return response()->json(['success' => false, 'message' => 'No valid columns selected.'], 400);
            }

            // Build query
            $query = DB::connection($connName)->table($table)->select($validColumns);

            // Apply filters
            foreach ($filters as $filter) {
                $field = $filter['field'] ?? null;
                $op    = $filter['operator'] ?? 'eq';
                $value = $filter['value'] ?? null;

                if (!$field || $value === null || $value === '' || !in_array($field, $availableColumns)) {
                    continue;
                }

                switch ($op) {
                    case 'eq':         $query->where($field, '=', $value); break;
                    case 'ne':         $query->where($field, '!=', $value); break;
                    case 'contains':   $query->where($field, 'like', '%' . $value . '%'); break;
                    case 'startswith': $query->where($field, 'like', $value . '%'); break;
                    case 'endswith':   $query->where($field, 'like', '%' . $value); break;
                    case 'gt':         $query->where($field, '>', $value); break;
                    case 'ge':         $query->where($field, '>=', $value); break;
                    case 'lt':         $query->where($field, '<', $value); break;
                    case 'le':         $query->where($field, '<=', $value); break;
                }
            }

            // Generate file
            $token = Str::random(32);
            $exportDir = storage_path('app/exports');
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }

            $baseFilename = $table . '_' . now()->format('Ymd_His');

            if ($format === 'xlsx') {
                $filePath = $exportDir . '/' . $baseFilename . '.xlsx';
                $this->generateExcel($query, $validColumns, $filePath, $table);
                $downloadName = $baseFilename . '.xlsx';
            } else {
                $filePath = $exportDir . '/' . $baseFilename . '.csv';
                $this->generateCsv($query, $validColumns, $filePath);
                $downloadName = $baseFilename . '.csv';
            }

            // Check file size — zip if > 10MB
            $fileSize = filesize($filePath);
            $zipped = false;

            if ($fileSize > 10 * 1024 * 1024) {
                $zipPath = $exportDir . '/' . $baseFilename . '.zip';
                $zip = new \ZipArchive();
                if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                    $zip->addFile($filePath, $downloadName);
                    $zip->close();
                    @unlink($filePath);
                    $filePath = $zipPath;
                    $downloadName = $baseFilename . '.zip';
                    $fileSize = filesize($filePath);
                    $zipped = true;
                }
            }

            // Store token mapping in cache (expires in 1 hour)
            \Illuminate\Support\Facades\Cache::put("report_download_{$token}", [
                'path'     => $filePath,
                'filename' => $downloadName,
            ], now()->addHour());

            return response()->json([
                'success'  => true,
                'token'    => $token,
                'filename' => $downloadName,
                'size'     => $this->formatBytes($fileSize),
                'zipped'   => $zipped,
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

        return response()->download($info['path'], $info['filename'])->deleteFileAfterSend(true);
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
