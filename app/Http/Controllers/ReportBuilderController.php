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

    public function __construct(ConnectionService $connectionService)
    {
        $this->connectionService = $connectionService;
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
            if (in_array($conn['type'] ?? '', ['mysql', 'sqlsrv', 'firebird']) && ($conn['is_active'] ?? true) && !empty($conn['is_connected'])) {
                $dbConnections[] = [
                    'key'  => $key,
                    'name' => $conn['name'] ?? $key,
                    'type' => $conn['type'],
                ];
                
                // Preload tables for this connection
                try {
                    $connName = $this->resolveConnection($key);
                    $driver = \Illuminate\Support\Facades\DB::connection($connName)->getDriverName();
                    
                    $tables = [];
                    if ($driver === 'mysql') {
                        $results = \Illuminate\Support\Facades\DB::connection($connName)->select("SHOW TABLES");
                        foreach ($results as $row) {
                            $tables[] = array_values((array) $row)[0];
                        }
                    } elseif ($driver === 'sqlsrv') {
                        $results = \Illuminate\Support\Facades\DB::connection($connName)->select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE IN ('BASE TABLE', 'VIEW')");
                        foreach ($results as $row) {
                            $rowArr = (array) $row;
                            $tables[] = $rowArr['TABLE_NAME'] ?? $rowArr['table_name'] ?? current($rowArr);
                        }
                    } elseif ($driver === 'firebird') {
                        $results = \Illuminate\Support\Facades\DB::connection($connName)->select("SELECT RDB\$RELATION_NAME FROM RDB\$RELATIONS WHERE RDB\$VIEW_BLR IS NULL AND (RDB\$SYSTEM_FLAG IS NULL OR RDB\$SYSTEM_FLAG = 0)");
                        foreach ($results as $row) {
                            $tables[] = trim(array_values((array) $row)[0]);
                        }
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
            if (in_array($conn['type'] ?? '', ['mysql', 'sqlsrv', 'firebird']) && ($conn['is_active'] ?? true) && !empty($conn['is_connected'])) {
                try {
                    // Resolve and test the connection
                    $connName = $this->resolveConnection($key);
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
            $connName = $this->resolveConnection($connKey);
            $pdo = DB::connection($connName)->getPdo();
            $driver = DB::connection($connName)->getDriverName();

            $tables = [];
            if ($driver === 'mysql') {
                $results = DB::connection($connName)->select("SHOW TABLES");
                foreach ($results as $row) {
                    $tables[] = array_values((array) $row)[0];
                }
            } elseif ($driver === 'sqlsrv') {
                $results = DB::connection($connName)->select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE IN ('BASE TABLE', 'VIEW')");
                foreach ($results as $row) {
                    $rowArr = (array) $row;
                    $tables[] = $rowArr['TABLE_NAME'] ?? $rowArr['table_name'] ?? current($rowArr);
                }
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
    public function columns(Request $request): JsonResponse
    {
        $connKey = $request->input('connection');
        $table   = $request->input('table');

        if (!$connKey || !$table) {
            return response()->json(['success' => false, 'message' => 'Connection and table are required.'], 400);
        }

        try {
            $connName = $this->resolveConnection($connKey);
            $columns = DB::connection($connName)->getSchemaBuilder()->getColumnListing($table);

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
            $connName = $this->resolveConnection($connKey);

            // Validate columns exist
            $availableColumns = DB::connection($connName)->getSchemaBuilder()->getColumnListing($table);
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
     * Resolve a connection key to a Laravel DB connection name.
     */
    private function resolveConnection(string $connKey): string
    {
        $conn = $this->connectionService->get($connKey);
        if (!$conn) {
            throw new \RuntimeException("Connection '{$connKey}' not found.");
        }

        $type = $conn['type'];
        $creds = $conn['credentials'] ?? [];

        $connName = 'report_builder_' . $connKey;

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

        config(["database.connections.{$connName}" => $mergedConfig]);

        return $connName;
    }

    /**
     * Generate a CSV file from the query.
     */
    private function generateCsv($query, array $columns, string $filePath): void
    {
        $file = fopen($filePath, 'w');
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
        fputcsv($file, $columns);

        foreach ($query->cursor() as $row) {
            $rowArray = [];
            foreach ($columns as $col) {
                $rowArray[] = $row->{$col} ?? '';
            }
            fputcsv($file, $rowArray);
        }

        fclose($file);
    }

    /**
     * Generate an Excel file from the query.
     */
    private function generateExcel($query, array $columns, string $filePath, string $sheetTitle): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));

        // Header row
        $colIndex = 1;
        foreach ($columns as $col) {
            $sheet->setCellValue([$colIndex, 1], $col);
            $colIndex++;
        }

        // Header style
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columns));
        $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F2937'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
        ]);

        // Data rows
        $rowNum = 2;
        foreach ($query->cursor() as $row) {
            $colIndex = 1;
            foreach ($columns as $col) {
                $sheet->setCellValue([$colIndex, $rowNum], $row->{$col} ?? '');
                $colIndex++;
            }
            $rowNum++;
        }

        // Auto-size columns (limit to first 30 for performance)
        foreach (array_slice($columns, 0, 30) as $i => $col) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }

        // Alternating row colors
        if ($rowNum > 2) {
            $dataRange = "A2:{$lastColLetter}" . ($rowNum - 1);
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'E5E7EB'],
                    ],
                ],
            ]);
            for ($r = 2; $r < $rowNum; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")->applyFromArray([
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F9FAFB'],
                        ],
                    ]);
                }
            }
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColLetter}1");

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filePath);
        $spreadsheet->disconnectWorksheets();
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
