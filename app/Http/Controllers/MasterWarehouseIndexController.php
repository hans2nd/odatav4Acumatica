<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QueryRepository;
use App\Models\Connection;
use Illuminate\Support\Facades\DB;

class MasterWarehouseIndexController extends Controller
{
    public function index(Request $request)
    {
        $queryId = 1;
        $queryRepo = QueryRepository::find($queryId);
        
        if (!$queryRepo) {
            return view('master-warehouse.index.index', ['error' => 'Query repository not found.']);
        }
        
        $conn = Connection::find($queryRepo->database_connection_id);
        if (!$conn) {
            return view('master-warehouse.index.index', ['error' => 'Database connection not found.']);
        }
        
        $config = [
            'driver'   => $conn->db_connection,
            'host'     => $conn->db_host,
            'port'     => $conn->db_port,
            'database' => $conn->db_database,
            'username' => $conn->db_username,
            'password' => $conn->db_password,
        ];
        config(['database.connections.dynamic_query_view' => $config]);
        
        $rawSql = $queryRepo->sql_text;
        $columns = is_array($queryRepo->output_column_json) ? $queryRepo->output_column_json : json_decode($queryRepo->output_column_json, true) ?? [];
        
        $query = DB::connection('dynamic_query_view')->query()->fromSub($rawSql, 'subquery');
        
        foreach ($columns as $col) {
            $field = is_array($col) ? ($col['field'] ?? null) : $col;
            if ($field && $request->filled($field)) {
                $query->where($field, 'LIKE', '%' . $request->input($field) . '%');
            }
        }
        
        try {
            // Using simplePaginate or paginate based on needs, paginate is standard
            $results = $query->paginate(20);
        } catch (\Exception $e) {
            return view('master-warehouse.index.index', ['error' => $e->getMessage()]);
        }
        
        return view('master-warehouse.index.index', [
            'results' => $results,
            'columns' => $columns,
        ]);
    }
}