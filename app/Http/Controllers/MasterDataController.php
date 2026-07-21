<?php

namespace App\Http\Controllers;

use App\Services\SchemaManager;
use App\Services\ConnectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterDataController extends Controller
{
    public function index(SchemaManager $schemaManager, ConnectionService $connectionService)
    {
        $connections = array_merge([
            'acumatica_default' => ['name' => 'Acumatica Default', 'type' => 'odata'],
            'database_default' => ['name' => 'Laravel Default DB', 'type' => 'database']
        ], $connectionService->getAll());

        $user = auth()->user();
        $entities = collect(config('acumatica.entities', []))
            ->filter(fn ($e, $key) => $e['type'] === 'master' && $user->hasPermission("{$key}.view"));

        $entityData = [];
        foreach ($entities as $key => $entity) {
            $connName = $entity['destination_connection'] ?? 'database_default';
            $schedule = \App\Models\SyncSchedule::where('entity_key', $key)->first();
            $entityData[$key] = [
                'config'       => $entity,
                'schedule'     => $schedule,
                'table_exists' => $schemaManager->tableExists($connName, $entity['table']),
                'records'      => $schemaManager->getRowCount($connName, $entity['table']),
                'custom_filters' => $schedule ? $schedule->custom_filters : [],
            ];
        }

        return view('master-data.index', [
            'entities'    => $entityData,
            'connections' => $connections,
            'activeTab'   => 'master-data',
        ]);
    }

    public function show(Request $request, SchemaManager $schemaManager, string $entity)
    {
        if (!$request->user()->hasPermission("{$entity}.view")) {
            abort(403, "You do not have permission to view {$entity}.");
        }

        $config = config("acumatica.entities.{$entity}");

        if (! $config || $config['type'] !== 'master') {
            abort(404, 'Entity not found.');
        }

        $tableName   = $config['table'];
        $connName    = $config['destination_connection'] ?? 'database_default';
        $tableExists = $schemaManager->tableExists($connName, $tableName);
        $data        = collect();
        $columns     = [];
        $search      = $request->input('search');

        if ($tableExists) {
            $firstRow = DB::connection($connName)->table($tableName)->first();
            $columns = $firstRow ? array_keys((array)$firstRow) : DB::connection($connName)->getSchemaBuilder()->getColumnListing($tableName);
            
            $query = DB::connection($connName)->table($tableName)->orderByDesc('_sync_id');

            if (!empty($search)) {
                $query->where(function ($q) use ($columns, $search) {
                    foreach ($columns as $col) {
                        if (!in_array($col, ['_sync_id', '_synced_at'])) {
                            $q->orWhere($col, 'like', '%' . $search . '%');
                        }
                    }
                });
            }

            $data = $query->paginate(50)->withQueryString();
        }

        return view('master-data.show', [
            'entityKey'   => $entity,
            'config'      => $config,
            'tableExists' => $tableExists,
            'columns'     => $columns,
            'data'        => $data,
            'activeTab'   => 'master-data',
        ]);
    }
}
