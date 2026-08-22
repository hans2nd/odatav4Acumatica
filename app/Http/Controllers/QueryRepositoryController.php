<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\QueryRepository;
use App\Models\Connection;
use App\Services\QueryRepositoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QueryRepositoryController extends Controller
{
    protected $queryService;

    public function __construct(QueryRepositoryService $queryService)
    {
        $this->queryService = $queryService;
    }

    public function index()
    {
        $queries = QueryRepository::with('connection')->latest()->get();
        return view('reports.query-repository.index', compact('queries'));
    }

    public function create()
    {
        $connections = Connection::where('is_active', true)->get();
        return view('reports.query-repository.form', compact('connections'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'database_connection_id' => 'required|exists:connections,id',
            'category' => 'nullable|string|max:100',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sql_text' => 'required|string',
        ]);

        $this->queryService->validateSql($validated['sql_text']);
        $parameters = $this->queryService->extractParameters($validated['sql_text']);

        $uiState = $request->input('ui_state_json') ? json_decode($request->input('ui_state_json'), true) : null;
        $outputColumns = $request->input('output_column_json') ? json_decode($request->input('output_column_json'), true) : [];

        $queryRepo = QueryRepository::create(array_merge($validated, [
            'uuid' => (string) Str::uuid(),
            'parameter_json' => $parameters,
            'ui_state_json' => $uiState,
            'output_column_json' => $outputColumns,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'version' => 1
        ]));

        return redirect()->route('query-repository.index')->with('success', 'Query saved successfully.');
    }

    public function edit($id)
    {
        $query = QueryRepository::findOrFail($id);
        $connections = Connection::where('is_active', true)->get();
        return view('reports.query-repository.form', compact('query', 'connections'));
    }

    public function update(Request $request, $id)
    {
        $queryRepo = QueryRepository::findOrFail($id);

        $validated = $request->validate([
            'database_connection_id' => 'required|exists:connections,id',
            'category' => 'nullable|string|max:100',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sql_text' => 'required|string',
            'is_active' => 'boolean'
        ]);

        $this->queryService->validateSql($validated['sql_text']);

        $newVersion = $queryRepo->version;
        if ($queryRepo->sql_text !== $validated['sql_text']) {
            $newVersion++;
        }

        $parameters = $this->queryService->extractParameters($validated['sql_text']);
        $uiState = $request->input('ui_state_json') ? json_decode($request->input('ui_state_json'), true) : null;
        $outputColumns = $request->input('output_column_json') ? json_decode($request->input('output_column_json'), true) : [];

        $queryRepo->update(array_merge($validated, [
            'parameter_json' => $parameters,
            'ui_state_json' => $uiState,
            'output_column_json' => $outputColumns,
            'updated_by' => Auth::id(),
            'version' => $newVersion,
            'is_active' => $request->has('is_active')
        ]));

        return redirect()->route('query-repository.index')->with('success', 'Query updated successfully.');
    }

    public function destroy($id)
    {
        $queryRepo = QueryRepository::findOrFail($id);
        $queryRepo->delete();

        return redirect()->route('query-repository.index')->with('success', 'Query deleted successfully.');
    }

    public function show($id)
    {
        $query = QueryRepository::with(['connection', 'logs.user'])->findOrFail($id);
        return view('reports.query-repository.show', compact('query'));
    }

    public function preview(Request $request)
    {
        $request->validate([
            'database_connection_id' => 'required|exists:connections,id',
            'sql_text' => 'required|string',
            'bindings' => 'nullable|array'
        ]);

        try {
            $connection = Connection::findOrFail($request->database_connection_id);
            $bindings = $request->input('bindings', []);

            $result = $this->queryService->executePreview($connection, $request->sql_text, $bindings);

            // Extract parameters again for preview response
            $parameters = $this->queryService->extractParameters($request->sql_text);

            // If we have an existing ID, log it
            if ($request->has('query_repository_id')) {
                $this->queryService->logExecution(
                    $request->query_repository_id,
                    Auth::id(),
                    $result['duration'],
                    true
                );
            }

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'columns' => $result['columns'],
                'parameters' => $parameters,
                'duration' => $result['duration'],
                'count' => $result['count']
            ]);

        } catch (\Throwable $e) {
            if ($request->has('query_repository_id')) {
                $this->queryService->logExecution(
                    $request->query_repository_id,
                    Auth::id(),
                    0,
                    false,
                    $e->getMessage()
                );
            }
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function export(Request $request)
    {
        $request->validate([
            'database_connection_id' => 'required|exists:connections,id',
            'sql_text' => 'required|string',
            'bindings' => 'nullable|array',
            'format' => 'required|in:csv,xlsx',
        ]);

        try {
            $connection = Connection::findOrFail($request->database_connection_id);
            $bindings = $request->input('bindings', []);

            $result = $this->queryService->executePreview($connection, $request->sql_text, $bindings);

            $rows = $result['data'];
            $columns = array_column($result['columns'], 'name');

            $token = Str::random(32);
            $exportDir = storage_path('app/exports');
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }

            $baseFilename = 'query_export_' . now()->format('Ymd_His');

            if ($request->input('format') === 'xlsx') {
                $filePath = $exportDir . '/' . $baseFilename . '.xlsx';
                $this->generateExcel($rows, $columns, $filePath);
                $downloadName = $baseFilename . '.xlsx';
            } else {
                $filePath = $exportDir . '/' . $baseFilename . '.csv';
                $this->generateCsv($rows, $columns, $filePath);
                $downloadName = $baseFilename . '.csv';
            }

            Cache::put("query_download_{$token}", [
                'path' => $filePath,
                'filename' => $downloadName,
            ], now()->addHour());

            return response()->json([
                'success' => true,
                'token' => $token,
                'filename' => $downloadName,
                'size' => $this->formatBytes(filesize($filePath)),
            ]);
        } catch (\Throwable $e) {
            Log::error('Query Repository export failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }

    public function download(string $token)
    {
        $info = Cache::get("query_download_{$token}");

        if (!$info || !file_exists($info['path'])) {
            abort(404, 'Download link has expired or file not found.');
        }

        return response()->download($info['path'], $info['filename'])->deleteFileAfterSend(true);
    }

    public function getColumns($id)
    {
        $queryRepo = QueryRepository::findOrFail($id);
        $columns = is_array($queryRepo->output_column_json) ? $queryRepo->output_column_json : json_decode($queryRepo->output_column_json, true) ?? [];
        
        $normalized = [];
        foreach ($columns as $col) {
            if (is_array($col)) {
                $field = $col['field'] ?? null;
                $label = $col['label'] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $field));
                $type = $col['type'] ?? 'text';
            } else {
                $field = $col;
                $label = \Illuminate\Support\Str::title(str_replace('_', ' ', $field));
                $type = 'text';
            }
            
            if ($field) {
                $normalized[] = [
                    'field' => $field,
                    'label' => $label,
                    'type' => $type
                ];
            }
        }

        return response()->json([
            'success' => true,
            'query' => [
                'id' => $queryRepo->id,
                'name' => $queryRepo->name
            ],
            'columns' => $normalized
        ]);
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
