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

class GrnIndexController extends Controller
{
    public function index(Request $request, MenuFilterService $filterService, \App\Services\ConnectionService $connService)
    {
        $queryId = 7;
        $menuId = 39;
        $queryRepo = QueryRepository::find($queryId);
        $menu = Menu::find($menuId);
        
        if (!$queryRepo) {
            return view('grn.index.index', ['error' => 'Query repository not found.']);
        }
        
        $conn = Connection::find($queryRepo->database_connection_id);
        if (!$conn) {
            return view('grn.index.index', ['error' => 'Database connection not found.']);
        }
        
        try {
            $laravelConnName = $connService->resolveConnectionName($conn->key);
        } catch (\Exception $e) {
            return view('grn.index.index', ['error' => 'Failed to resolve connection: ' . $e->getMessage()]);
        }
        
        $rawSql = $queryRepo->sql_text;
        $columns = is_array($queryRepo->output_column_json) ? $queryRepo->output_column_json : json_decode($queryRepo->output_column_json, true) ?? [];
        
        $query = DB::connection($laravelConnName)->query()->fromSub($rawSql, 'subquery');
        
        $filterConfig = $menu ? $menu->filter_config : null;
        
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
                return view('grn.index.index', [
                    'error' => collect($e->errors())->flatten()->first(),
                    'columns' => $columns,
                    'filterConfig' => $filterConfig,
                    'results' => collect([]), // Empty results on validation error
                ]);
            }
        }
        if ($request->input('export') === 'csv' || $request->input('export') === 'xlsx') {
            $format = $request->input('export');
            $fileName = ($menu ? Str::slug($menu->name) : 'export') . '_' . date('Ymd_His') . '.' . $format;
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
            return view('grn.index.index', ['error' => $e->getMessage()]);
        }
        
        return view('grn.index.index', [
            'results' => $results,
            'columns' => $columns,
            'filterConfig' => $filterConfig,
        ]);
    }
}