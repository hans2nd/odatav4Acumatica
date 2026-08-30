<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiEndpoint;
use App\Services\ConnectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DataEndpointController extends Controller
{
    protected ConnectionService $connectionService;

    public function __construct(ConnectionService $connectionService)
    {
        $this->connectionService = $connectionService;
    }

    public function getData(Request $request, $slug)
    {
        $endpoint = ApiEndpoint::where('endpoint_slug', $slug)->where('is_active', true)->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found or inactive.'
            ], 404);
        }

        $permName = "api.endpoint.{$slug}.view";
        if (!$request->user() || !$request->user()->hasPermission($permName)) {
            return response()->json([
                'success' => false,
                'message' => "Unauthorized. Missing permission: {$permName}"
            ], 403);
        }

        try {
            // Mencegah timeout dan memory limit saat streaming jutaan data ke Power BI
            set_time_limit(0);
            ini_set('memory_limit', '2048M'); // Atau '-1' untuk unlimited

            $connName = $this->connectionService->resolveConnectionName($endpoint->connection_key);

            // Allow dynamic per_page from query, default to 1000
            $perPage = $request->query('per_page', 1000);
            if ($perPage > 5000)
                $perPage = 5000; // Cap it

            $query = DB::connection($connName)->table($endpoint->table_name);

            if (!empty($endpoint->columns) && is_array($endpoint->columns)) {
                $query->select($endpoint->columns);
            }

            if (!empty($endpoint->filters) && is_array($endpoint->filters)) {
                foreach ($endpoint->filters as $filter) {
                    $field = $filter['field'] ?? null;
                    $op = $filter['operator'] ?? 'eq';
                    $val = $filter['value'] ?? '';

                    if ($field) {
                        $isDateField = preg_match('/date|time|_at|dt|tgl|tanggal/i', $field);
                        if ($isDateField && preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                            switch ($op) {
                                case 'eq':
                                    $query->whereDate($field, '=', $val);
                                    break;
                                case 'ne':
                                    $query->whereDate($field, '!=', $val);
                                    break;
                                case 'lt':
                                    $query->whereDate($field, '<', $val);
                                    break;
                                case 'le':
                                    $query->whereDate($field, '<=', $val);
                                    break;
                                case 'gt':
                                    $query->whereDate($field, '>', $val);
                                    break;
                                case 'ge':
                                    $query->whereDate($field, '>=', $val);
                                    break;
                                default:
                                    $query->whereDate($field, '=', $val);
                                    break;
                            }
                        } else {
                            switch ($op) {
                                case 'eq':
                                    $query->where($field, '=', $val);
                                    break;
                                case 'ne':
                                    $query->where($field, '!=', $val);
                                    break;
                                case 'contains':
                                    $query->where($field, 'LIKE', '%' . $val . '%');
                                    break;
                                case 'lt':
                                    $query->where($field, '<', $val);
                                    break;
                                case 'le':
                                    $query->where($field, '<=', $val);
                                    break;
                                case 'gt':
                                    $query->where($field, '>', $val);
                                    break;
                                case 'ge':
                                    $query->where($field, '>=', $val);
                                    break;
                            }
                        }
                    }
                }
            }

            // Execute the query
            // If user requests a limit, apply it to prevent huge memory spikes, otherwise get all.
            $limit = $request->query('limit');
            if ($limit) {
                $query->limit($limit);
            }

            // Stream the JSON response to prevent memory exhaustion (500 errors) on large tables
            return response()->stream(function () use ($query, $endpoint) {
                $sql = $query->toSql();
                $bindings = $query->getBindings();
                $cursor = app(\App\Services\QueryRepositoryService::class)->executeStream($endpoint->connection_key, $sql, $bindings);

                echo '{"data":[';
                $first = true;
                foreach ($cursor as $row) {
                    if (!$first) {
                        echo ',';
                    }

                    // Convert numeric strings to actual floats and dates to ISO 8601 
                    // so Power BI / Excel treats them as native Numbers/Dates and formats them by the user's Region.
                    foreach ((array) $row as $col => $val) {
                        if (is_numeric($val) && strpos($col, 'ID') === false && strpos($col, 'CD') === false && strpos($col, 'Nbr') === false && strpos(strtolower($col), 'phone') === false) {
                            $row->{$col} = (float) $val;
                        } elseif (is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2}(\.\d+)?)?$/', $val)) {
                            try {
                                $row->{$col} = \Carbon\Carbon::parse($val)->format('Y-m-d\TH:i:s');
                            } catch (\Throwable $e) {
                            }
                        }
                    }

                    echo json_encode($row);
                    $first = false;
                }
                echo ']}';
            }, 200, [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache, must-revalidate'
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch data: ' . $e->getMessage()
            ], 500);
        }
    }


}
