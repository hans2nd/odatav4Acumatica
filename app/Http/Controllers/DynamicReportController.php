<?php

namespace App\Http\Controllers;

use App\Services\EntityConfigService;
use App\Services\SchemaManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DynamicReportController extends Controller
{
    /**
     * Show a dynamic report for any synced transaction entity.
     * Supports date filtering, column search, and Excel export.
     */
    public function show(Request $request, SchemaManager $schemaManager, string $entityKey)
    {
        $config = config("acumatica.entities.{$entityKey}");

        if (!$config || $config['type'] !== 'transaction') {
            abort(404, 'Report entity not found.');
        }

        $tableName   = $config['table'];
        $tableExists = $schemaManager->tableExists($tableName);
        $columns     = [];
        $data        = collect();
        $dateColumns = [];

        if ($tableExists) {
            $columns = $this->getVisibleColumns($tableName);
            $dateColumns = $this->detectDateColumns($tableName, $columns);

            $query = DB::table($tableName);

            // Date range filter
            $dateColumn = $request->input('date_column', $dateColumns[0] ?? null);
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');

            if ($dateColumn && $dateFrom) {
                $query->where($dateColumn, '>=', $dateFrom . 'T00:00:00');
            }
            if ($dateColumn && $dateTo) {
                $query->where($dateColumn, '<=', $dateTo . 'T23:59:59');
            }

            // Search across chosen column
            $searchColumn = $request->input('search_column');
            $searchValue  = $request->input('search');

            if ($searchValue) {
                if ($searchColumn && in_array($searchColumn, $columns)) {
                    $query->where($searchColumn, 'like', '%' . $searchValue . '%');
                } else {
                    // Search all visible columns
                    $query->where(function ($q) use ($columns, $searchValue) {
                        foreach (array_slice($columns, 0, 15) as $col) {
                            $q->orWhere($col, 'like', '%' . $searchValue . '%');
                        }
                    });
                }
            }

            $query->orderByDesc('_sync_id');
            $data = $query->paginate(50)->withQueryString();
        }

        return view('reports.dynamic', [
            'entityKey'    => $entityKey,
            'config'       => $config,
            'tableExists'  => $tableExists,
            'columns'      => $columns,
            'dateColumns'  => $dateColumns,
            'data'         => $data,
            'filters'      => [
                'date_column'   => $request->input('date_column', $dateColumns[0] ?? ''),
                'date_from'     => $request->input('date_from', ''),
                'date_to'       => $request->input('date_to', ''),
                'search_column' => $request->input('search_column', ''),
                'search'        => $request->input('search', ''),
            ],
            'activeTab' => 'report.' . $entityKey,
        ]);
    }

    /**
     * Export filtered data (CSV or Excel).
     */
    public function export(Request $request, SchemaManager $schemaManager, string $entityKey)
    {
        $format = $request->input('format', 'csv');
        $config = config("acumatica.entities.{$entityKey}");

        if (!$config || $config['type'] !== 'transaction') {
            abort(404, 'Report entity not found.');
        }

        $tableName = $config['table'];
        if (!$schemaManager->tableExists($tableName)) {
            abort(404, 'Table does not exist yet.');
        }

        $columns     = $this->getVisibleColumns($tableName);
        $dateColumns = $this->detectDateColumns($tableName, $columns);
        $query       = $this->buildFilteredQuery($request, $tableName, $columns, $dateColumns);

        if ($format === 'xlsx') {
            return $this->exportExcel($query, $columns, $config);
        }

        return $this->exportCsv($query, $columns, $config);
    }

    /**
     * Build filtered query from request (shared by show + export).
     */
    private function buildFilteredQuery(Request $request, string $tableName, array $columns, array $dateColumns)
    {
        $query = DB::table($tableName)->select($columns);

        $dateColumn = $request->input('date_column', $dateColumns[0] ?? null);
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');

        if ($dateColumn && $dateFrom) {
            $query->where($dateColumn, '>=', $dateFrom . 'T00:00:00');
        }
        if ($dateColumn && $dateTo) {
            $query->where($dateColumn, '<=', $dateTo . 'T23:59:59');
        }

        $searchColumn = $request->input('search_column');
        $searchValue  = $request->input('search');

        if ($searchValue) {
            if ($searchColumn && in_array($searchColumn, $columns)) {
                $query->where($searchColumn, 'like', '%' . $searchValue . '%');
            } else {
                $query->where(function ($q) use ($columns, $searchValue) {
                    foreach (array_slice($columns, 0, 15) as $col) {
                        $q->orWhere($col, 'like', '%' . $searchValue . '%');
                    }
                });
            }
        }

        $query->orderByDesc('_sync_id');

        return $query;
    }

    /**
     * Export as CSV (streaming).
     */
    private function exportCsv($query, array $columns, array $config)
    {
        $fileName = $config['table'] . '_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        $callback = function () use ($query, $columns) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, $columns);

            $query->chunk(500, function ($rows) use ($file, $columns) {
                foreach ($rows as $row) {
                    $rowArray = [];
                    foreach ($columns as $col) {
                        $rowArray[] = $row->{$col} ?? '';
                    }
                    fputcsv($file, $rowArray);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export as Excel (.xlsx) using PhpSpreadsheet.
     */
    private function exportExcel($query, array $columns, array $config)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($config['description'], 0, 31));

        // Header row with styling
        $colIndex = 1;
        foreach ($columns as $col) {
            $cell = $sheet->setCellValue([$colIndex, 1], $col);
            $colIndex++;
        }

        $headerStyle = [
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
        ];

        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columns));
        $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray($headerStyle);

        // Data rows
        $rowNum = 2;
        $query->chunk(500, function ($rows) use ($sheet, $columns, &$rowNum) {
            foreach ($rows as $row) {
                $colIndex = 1;
                foreach ($columns as $col) {
                    $value = $row->{$col} ?? '';
                    $sheet->setCellValue([$colIndex, $rowNum], $value);
                    $colIndex++;
                }
                $rowNum++;
            }
        });

        // Auto-size columns (limit to first 30 cols for performance)
        foreach (array_slice($columns, 0, 30) as $i => $col) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }

        // Data border styling
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
            // Alternating row colors
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

        // Freeze header row
        $sheet->freezePane('A2');
        // Auto-filter
        $sheet->setAutoFilter("A1:{$lastColLetter}1");

        $fileName = $config['table'] . '_' . now()->format('Ymd_His') . '.xlsx';

        $tempPath = storage_path('app/' . $fileName);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempPath);
        $spreadsheet->disconnectWorksheets();

        return response()->download($tempPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Get list of synced transaction entities for the sidebar.
     */
    public static function getSyncedTransactionEntities(): array
    {
        $entities = config('acumatica.entities', []);
        $synced = [];

        foreach ($entities as $key => $entity) {
            if (($entity['type'] ?? '') !== 'transaction') {
                continue;
            }

            if (Schema::hasTable($entity['table']) && DB::table($entity['table'])->exists()) {
                $synced[$key] = $entity;
            }
        }

        return $synced;
    }

    /**
     * Get visible columns (exclude internal ones).
     */
    private function getVisibleColumns(string $tableName): array
    {
        $allColumns = DB::getSchemaBuilder()->getColumnListing($tableName);
        return array_values(array_filter($allColumns, function ($col) {
            return !in_array($col, ['_sync_id', '_synced_at', '_row_hash']);
        }));
    }

    /**
     * Auto-detect columns that likely contain dates (by naming convention).
     */
    private function detectDateColumns(string $tableName, array $columns): array
    {
        $dateKeywords = ['date', 'time', 'created', 'modified', 'shipped', 'order'];
        $dateColumns = [];

        foreach ($columns as $col) {
            $lower = strtolower($col);
            foreach ($dateKeywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    $dateColumns[] = $col;
                    break;
                }
            }
        }

        return $dateColumns;
    }
}
