<?php

namespace App\Services;

use App\Filters\LogFilter;
use App\Models\SyncLog;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SyncLogExportService
{
    private LogFilter $logFilter;

    public function __construct(LogFilter $logFilter)
    {
        $this->logFilter = $logFilter;
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $query = SyncLog::query();
        $query = $this->logFilter->apply($query, $request);

        $response = new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');
            
            // CSV Header
            fputcsv($handle, [
                'ID', 'Entity', 'OData Entity', 'Type', 'Status', 'Current Stage',
                'Rows Fetched', 'Rows Inserted', 'Rows Failed', 'Duration (s)',
                'Rows/sec', 'Peak Memory (MB)', 'Started At', 'Completed At', 'Error'
            ]);

            // Chunk to avoid memory limits for large datasets
            $query->chunk(1000, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->entity_key,
                        $log->odata_entity,
                        $log->type,
                        $log->status,
                        $log->current_stage,
                        $log->records_fetched,
                        $log->records_inserted,
                        $log->records_failed,
                        $log->duration_seconds,
                        $log->rows_per_second,
                        $log->peak_memory_mb,
                        $log->started_at,
                        $log->completed_at,
                        $log->error_message
                    ]);
                }
            });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="sync_logs_export.csv"');

        return $response;
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $query = SyncLog::query();
        $query = $this->logFilter->apply($query, $request);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $headers = [
            'A1' => 'ID', 'B1' => 'Entity', 'C1' => 'OData Entity', 'D1' => 'Type',
            'E1' => 'Status', 'F1' => 'Current Stage', 'G1' => 'Rows Fetched',
            'H1' => 'Rows Inserted', 'I1' => 'Rows Failed', 'J1' => 'Duration (s)',
            'K1' => 'Rows/sec', 'L1' => 'Peak Memory (MB)', 'M1' => 'Started At',
            'N1' => 'Completed At', 'O1' => 'Error'
        ];
        
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        // Data
        $rowNum = 2;
        // Limit Excel to prevent huge memory spikes if user exports millions of rows without filter.
        // Usually, chunking with PhpSpreadsheet is still heavy on memory since the whole object is in RAM.
        // We will process max 50,000 rows for Excel.
        $query->limit(50000)->chunk(1000, function ($logs) use ($sheet, &$rowNum) {
            foreach ($logs as $log) {
                $sheet->setCellValue('A' . $rowNum, $log->id);
                $sheet->setCellValue('B' . $rowNum, $log->entity_key);
                $sheet->setCellValue('C' . $rowNum, $log->odata_entity);
                $sheet->setCellValue('D' . $rowNum, $log->type);
                $sheet->setCellValue('E' . $rowNum, $log->status);
                $sheet->setCellValue('F' . $rowNum, $log->current_stage);
                $sheet->setCellValue('G' . $rowNum, $log->records_fetched);
                $sheet->setCellValue('H' . $rowNum, $log->records_inserted);
                $sheet->setCellValue('I' . $rowNum, $log->records_failed);
                $sheet->setCellValue('J' . $rowNum, $log->duration_seconds);
                $sheet->setCellValue('K' . $rowNum, $log->rows_per_second);
                $sheet->setCellValue('L' . $rowNum, $log->peak_memory_mb);
                $sheet->setCellValue('M' . $rowNum, $log->started_at);
                $sheet->setCellValue('N' . $rowNum, $log->completed_at);
                $sheet->setCellValue('O' . $rowNum, $log->error_message);
                $rowNum++;
            }
        });

        $writer = new Xlsx($spreadsheet);
        
        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="sync_logs_export.xlsx"');

        return $response;
    }
}
