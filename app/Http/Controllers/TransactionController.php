<?php

namespace App\Http\Controllers;

use App\Services\SchemaManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(SchemaManager $schemaManager)
    {
        $entities = collect(config('acumatica.entities', []))
            ->filter(fn ($e) => $e['type'] === 'transaction');

        $entityData = [];
        foreach ($entities as $key => $entity) {
            $entityData[$key] = [
                'config'       => $entity,
                'table_exists' => $schemaManager->tableExists($entity['table']),
                'records'      => $schemaManager->getRowCount($entity['table']),
            ];
        }

        return view('transactions.index', [
            'entities'  => $entityData,
            'activeTab' => 'transactions',
        ]);
    }

    public function show(Request $request, SchemaManager $schemaManager, string $entity)
    {
        $config = config("acumatica.entities.{$entity}");

        if (! $config || $config['type'] !== 'transaction') {
            abort(404, 'Entity not found.');
        }

        $tableName   = $config['table'];
        $tableExists = $schemaManager->tableExists($tableName);
        $data        = collect();
        $columns     = [];
        $search      = $request->input('search');

        if ($tableExists) {
            $columns = DB::getSchemaBuilder()->getColumnListing($tableName);
            
            $query = DB::table($tableName)->orderByDesc('_sync_id');

            if (!empty($search)) {
                $query->where(function ($q) use ($columns, $search) {
                    foreach ($columns as $col) {
                        if (!in_array($col, ['_sync_id', '_synced_at'])) {
                            $q->orWhere($col, 'like', '%' . $search . '%');
                        }
                    }
                });
            }

            $data = $query->paginate(20)->withQueryString();
        }

        return view('transactions.show', [
            'entityKey'   => $entity,
            'config'      => $config,
            'tableExists' => $tableExists,
            'columns'     => $columns,
            'data'        => $data,
            'activeTab'   => 'transactions',
        ]);
    }
}
