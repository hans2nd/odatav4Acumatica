<?php

namespace App\Http\Controllers;

use App\Services\ConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class QueryBuilderController extends Controller
{
    protected ConnectionService $connectionService;
    protected \App\Services\Connections\ConnectionManager $connectionManager;

    public function __construct(ConnectionService $connectionService, \App\Services\Connections\ConnectionManager $connectionManager)
    {
        $this->connectionService = $connectionService;
        $this->connectionManager = $connectionManager;
    }

    /**
     * Show the Query Builder page.
     */
    public function index()
    {
        $all = $this->connectionService->getAll();
        $dbConnections = [];

        foreach ($all as $key => $conn) {
            if (in_array($conn['type'] ?? '', \App\Services\Connections\ConnectionProviderFactory::getDatabaseTypes()) && ($conn['is_active'] ?? true) && !empty($conn['is_connected'])) {
                $dbConnections[] = [
                    'key'  => $key,
                    'name' => $conn['name'] ?? $key,
                    'type' => $conn['type'],
                ];
            }
        }

        return view('reports.query-builder', [
            'activeTab'   => 'query.builder',
            'connections' => $dbConnections,
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
     * List columns with data types for a given connection + table.
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

            $columns = [];
            foreach ($result->fields as $field) {
                $columns[] = [
                    'name' => $field['name'],
                    'type' => $field['dataType'] ?? 'varchar',
                    'key'  => $field['isPrimaryKey'] ? 'PRI' : '',
                ];
            }

            return response()->json(['success' => true, 'columns' => $columns]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch columns: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Execute the built query and return paginated results.
     */
    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            'connection'   => 'required|string',
            'raw_sql'      => 'nullable|string',
            'tables'       => 'nullable|array',
            'columns'      => 'nullable|array',
            'joins'        => 'nullable|array',
            'filters'      => 'nullable|array',
            'orderBy'      => 'nullable|array',
            'groupBy'      => 'nullable|array',
            'limit'        => 'nullable|integer|min:1|max:10000',
        ]);

        $connKey  = $request->input('connection');
        $rawSql   = $request->input('raw_sql');
        $tables   = $request->input('tables', []);
        $columns  = $request->input('columns', []);
        $joins    = $request->input('joins', []);
        $filters  = $request->input('filters', []);
        $orderBy  = $request->input('orderBy', []);
        $groupBy  = $request->input('groupBy', []);
        $limit    = $request->input('limit', 500);

        try {
            $connName = $this->connectionService->resolveConnectionName($connKey);
            $driver = DB::connection($connName)->getDriverName();
            
            if (!empty($rawSql)) {
                $sql = $rawSql;
                $bindings = [];
            } else {
                if (empty($tables) || empty($columns)) {
                    throw new \Exception("Tables and columns are required when not using raw SQL.");
                }
                
                $q = $driver === 'mysql' ? '`' : ($driver === 'firebird' ? '' : '[');
                $qe = $driver === 'mysql' ? '`' : ($driver === 'firebird' ? '' : ']');

                // Build the SELECT clause
            $selectParts = [];
            foreach ($columns as $col) {
                $table = $col['table'] ?? $tables[0];
                $field = $col['field'];
                $alias = $col['alias'] ?? null;
                $aggregate = $col['aggregate'] ?? null;

                $colRef = "{$q}{$table}{$qe}.{$q}{$field}{$qe}";

                if ($aggregate && in_array(strtoupper($aggregate), ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'])) {
                    $colRef = strtoupper($aggregate) . "({$colRef})";
                    $alias = $alias ?: strtolower($aggregate) . '_' . $field;
                }

                if ($alias) {
                    $selectParts[] = "{$colRef} AS {$q}{$alias}{$qe}";
                } else {
                    $selectParts[] = $colRef;
                }
            }

            $selectClause = implode(', ', $selectParts);

            // Build FROM clause
            $primaryTable = $tables[0];
            $fromClause = "{$q}{$primaryTable}{$qe}";

            // Build JOINs
            $joinClause = '';
            foreach ($joins as $join) {
                $joinType = strtoupper($join['type'] ?? 'INNER') . ' JOIN';
                $joinTable = $join['table'];
                $onLeft = $join['on_left'];
                $onRight = $join['on_right'];

                $leftParts = explode('.', $onLeft);
                $rightParts = explode('.', $onRight);

                $leftRef = "{$q}{$leftParts[0]}{$qe}.{$q}{$leftParts[1]}{$qe}";
                $rightRef = "{$q}{$rightParts[0]}{$qe}.{$q}{$rightParts[1]}{$qe}";

                $joinClause .= " {$joinType} {$q}{$joinTable}{$qe} ON {$leftRef} = {$rightRef}";
            }

            // Build WHERE clause
            $whereClause = '';
            $bindings = [];
            $filterIdx = 0;
            foreach ($filters as $filter) {
                $field = $filter['field'] ?? null;
                $op    = $filter['operator'] ?? 'eq';
                $value = $filter['value'] ?? null;

                if (!$field) continue;
                if (!in_array($op, ['is_null', 'not_null']) && ($value === null || $value === '')) continue;

                $parts = explode('.', $field);
                $fieldRef = count($parts) === 2
                    ? "{$q}{$parts[0]}{$qe}.{$q}{$parts[1]}{$qe}"
                    : "{$q}{$field}{$qe}";

                $connector = $filterIdx === 0 ? 'WHERE' : 'AND';

                switch ($op) {
                    case 'eq':         $whereClause .= " {$connector} {$fieldRef} = ?"; $bindings[] = $value; break;
                    case 'ne':         $whereClause .= " {$connector} {$fieldRef} != ?"; $bindings[] = $value; break;
                    case 'contains':   $whereClause .= " {$connector} {$fieldRef} LIKE ?"; $bindings[] = "%{$value}%"; break;
                    case 'startswith': $whereClause .= " {$connector} {$fieldRef} LIKE ?"; $bindings[] = "{$value}%"; break;
                    case 'endswith':   $whereClause .= " {$connector} {$fieldRef} LIKE ?"; $bindings[] = "%{$value}"; break;
                    case 'gt':         $whereClause .= " {$connector} {$fieldRef} > ?"; $bindings[] = $value; break;
                    case 'ge':         $whereClause .= " {$connector} {$fieldRef} >= ?"; $bindings[] = $value; break;
                    case 'lt':         $whereClause .= " {$connector} {$fieldRef} < ?"; $bindings[] = $value; break;
                    case 'le':         $whereClause .= " {$connector} {$fieldRef} <= ?"; $bindings[] = $value; break;
                    case 'is_null':    $whereClause .= " {$connector} {$fieldRef} IS NULL"; break;
                    case 'not_null':   $whereClause .= " {$connector} {$fieldRef} IS NOT NULL"; break;
                }
                $filterIdx++;
            }

            // Build GROUP BY clause
            $groupByClause = '';
            if (!empty($groupBy)) {
                $gbParts = [];
                foreach ($groupBy as $gb) {
                    $parts = explode('.', $gb);
                    $gbParts[] = count($parts) === 2
                        ? "{$q}{$parts[0]}{$qe}.{$q}{$parts[1]}{$qe}"
                        : "{$q}{$gb}{$qe}";
                }
                $groupByClause = ' GROUP BY ' . implode(', ', $gbParts);
            }

            // Build ORDER BY clause
            $orderByClause = '';
            if (!empty($orderBy)) {
                $obParts = [];
                foreach ($orderBy as $ob) {
                    $field = $ob['field'];
                    $dir   = strtoupper($ob['direction'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
                    $parts = explode('.', $field);
                    $fieldRef = count($parts) === 2
                        ? "{$q}{$parts[0]}{$qe}.{$q}{$parts[1]}{$qe}"
                        : "{$q}{$field}{$qe}";
                    $obParts[] = "{$fieldRef} {$dir}";
                }
                $orderByClause = ' ORDER BY ' . implode(', ', $obParts);
            }

            // Build LIMIT
            $limitClause = '';
            if ($driver === 'mysql') {
                $limitClause = " LIMIT {$limit}";
            } elseif ($driver === 'sqlsrv') {
                if (empty($orderByClause)) {
                    $orderByClause = " ORDER BY (SELECT NULL)";
                }
                $limitClause = " OFFSET 0 ROWS FETCH NEXT {$limit} ROWS ONLY";
            } elseif ($driver === 'firebird') {
                $limitClause = " ROWS 1 TO {$limit}";
            }

            $sql = "SELECT {$selectClause} FROM {$fromClause}{$joinClause}{$whereClause}{$groupByClause}{$orderByClause}{$limitClause}";
            } // end else for rawSql

            $startTime = microtime(true);
            $results = DB::connection($connName)->select($sql, $bindings);
            $elapsed = round((microtime(true) - $startTime) * 1000);

            $rows = array_map(fn($r) => (array) $r, $results);
            $resultColumns = !empty($rows) ? array_keys($rows[0]) : [];

            return response()->json([
                'success'     => true,
                'sql'         => $sql,
                'columns'     => $resultColumns,
                'rows'        => $rows,
                'row_count'   => count($rows),
                'elapsed_ms'  => $elapsed,
            ]);
        } catch (\Throwable $e) {
            Log::error('Query Builder execute failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Query failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export query results to CSV/XLSX.
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'connection' => 'required|string',
            'sql'        => 'required|string',
            'format'     => 'required|in:csv,xlsx',
        ]);

        $connKey = $request->input('connection');
        $sql     = $request->input('sql');
        $format  = $request->input('format');

        try {
            $connName = $this->connectionService->resolveConnectionName($connKey);

            if (!preg_match('/^\s*SELECT\s/i', $sql)) {
                return response()->json(['success' => false, 'message' => 'Only SELECT queries are allowed.'], 400);
            }

            $results = DB::connection($connName)->select($sql);
            $rows = array_map(fn($r) => (array) $r, $results);
            $columns = !empty($rows) ? array_keys($rows[0]) : [];

            $token = Str::random(32);
            $exportDir = storage_path('app/exports');
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }

            $baseFilename = 'query_export_' . now()->format('Ymd_His');

            if ($format === 'xlsx') {
                $filePath = $exportDir . '/' . $baseFilename . '.xlsx';
                $this->generateExcel($rows, $columns, $filePath);
                $downloadName = $baseFilename . '.xlsx';
            } else {
                $filePath = $exportDir . '/' . $baseFilename . '.csv';
                $this->generateCsv($rows, $columns, $filePath);
                $downloadName = $baseFilename . '.csv';
            }

            Cache::put("query_download_{$token}", [
                'path'     => $filePath,
                'filename' => $downloadName,
            ], now()->addHour());

            return response()->json([
                'success'  => true,
                'token'    => $token,
                'filename' => $downloadName,
                'size'     => $this->formatBytes(filesize($filePath)),
            ]);
        } catch (\Throwable $e) {
            Log::error('Query Builder export failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Download a prepared export file.
     */
    public function download(string $token)
    {
        $info = Cache::get("query_download_{$token}");

        if (!$info || !file_exists($info['path'])) {
            abort(404, 'Download link has expired or file not found.');
        }

        return response()->download($info['path'], $info['filename'])->deleteFileAfterSend(true);
    }

    // --- Private Helpers ---


    private function generateCsv(array $rows, array $columns, string $filePath): void
    {
        $file = fopen($filePath, 'w');
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($file, $columns);

        foreach ($rows as $row) {
            $rowArray = [];
            foreach ($columns as $col) {
                $rowArray[] = $row[$col] ?? '';
            }
            fputcsv($file, $rowArray);
        }

        fclose($file);
    }

    private function generateExcel(array $rows, array $columns, string $filePath): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Query Result');

        $colIndex = 1;
        foreach ($columns as $col) {
            $sheet->setCellValue([$colIndex, 1], $col);
            $colIndex++;
        }

        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columns));
        $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F2937'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        $rowNum = 2;
        foreach ($rows as $row) {
            $colIndex = 1;
            foreach ($columns as $col) {
                $sheet->setCellValue([$colIndex, $rowNum], $row[$col] ?? '');
                $colIndex++;
            }
            $rowNum++;
        }

        foreach (array_slice($columns, 0, 30) as $i => $col) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColLetter}1");

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filePath);
        $spreadsheet->disconnectWorksheets();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' bytes';
    }
}
