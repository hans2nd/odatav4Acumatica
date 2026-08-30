<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QueryRepository;
use App\Models\Connection;
use Illuminate\Support\Facades\DB;
use App\Models\Menu;
use App\Services\MenuFilterService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class DynamicReportController extends Controller
{
    public function index(Request $request, MenuFilterService $filterService, \App\Services\ConnectionService $connService, $menuId)
    {
        $menu = Menu::findOrFail($menuId);
        
        if (!$menu->hasAccess(auth()->user())) {
            abort(403, 'Unauthorized access to this report.');
        }

        $queryId = $menu->query_repository_id;
        
        if (!$queryId) {
            return view('reports.dynamic', ['error' => 'This menu is not linked to a Query Repository.', 'title' => $menu->title]);
        }

        $queryRepo = QueryRepository::find($queryId);
        if (!$queryRepo) {
            return view('reports.dynamic', ['error' => 'Query repository not found.', 'title' => $menu->title]);
        }
        
        $conn = Connection::find($queryRepo->database_connection_id);
        if (!$conn) {
            return view('reports.dynamic', ['error' => 'Database connection not found.', 'title' => $menu->title]);
        }
        
        $rawSql = $queryRepo->sql_text;
        $columns = is_array($queryRepo->output_column_json) ? $queryRepo->output_column_json : json_decode($queryRepo->output_column_json, true) ?? [];
        
        $qrService = app(\App\Services\QueryRepositoryService::class);
        try {
            $query = $qrService->executeBuilder($conn->key, $rawSql, $request->all());
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('DynamicReport execution failed', ['error' => $e->getMessage(), 'menu_id' => $menuId]);
            return view('reports.dynamic', ['error' => 'Database execution failed. Please check the system logs or verify the query definition.', 'title' => $menu->title]);
        }
        
        $filterConfig = $menu->filter_config;
        
        if ($filterConfig === null) {
            // Legacy behavior
            foreach ($columns as $col) {
                $field = is_array($col) ? ($col['field'] ?? null) : $col;
                if ($field && $request->filled($field)) {
                    $query->where($field, 'LIKE', '%' . $request->input($field) . '%');
                }
            }
        } elseif (is_array($filterConfig) && count($filterConfig) > 0) {
            try {
                $filterService->applyFilters($query, $filterConfig, $request);
            } catch (ValidationException $e) {
                return view('reports.dynamic', [
                    'error' => collect($e->errors())->flatten()->first(),
                    'columns' => $columns,
                    'filterConfig' => $filterConfig,
                    'results' => collect([]), // Empty results on validation error
                    'title' => $menu->title
                ]);
            }
        }
        if ($request->input('export') === 'csv' || $request->input('export') === 'xlsx') {
            $format = $request->input('export');
            $fileName = Str::slug($menu->title) . '_' . date('Ymd_His') . '.' . $format;
            $writer = \Spatie\SimpleExcel\SimpleExcelWriter::streamDownload($fileName);
            
            // For XLSX, Spatie uses Spout which writes to temp disk then streams, preventing memory issues.
            foreach ($query->cursor() as $row) {
                $writer->addRow((array) $row);
            }
            return $writer->toBrowser();
        }
        
        try {
            // Using simplePaginate for huge datasets
            $results = $query->simplePaginate(20)->withQueryString();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('DynamicReport pagination failed', ['error' => $e->getMessage(), 'menu_id' => $menuId]);
            return view('reports.dynamic', ['error' => 'Failed to paginate results. The query may be invalid or incompatible with pagination.', 'title' => $menu->title]);
        }
        
        return view('reports.dynamic', [
            'results' => $results,
            'columns' => $columns,
            'filterConfig' => $filterConfig,
            'title' => $menu->title
        ]);
    }
}
