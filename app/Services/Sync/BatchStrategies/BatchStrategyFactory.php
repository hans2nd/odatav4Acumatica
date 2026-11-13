<?php

namespace App\Services\Sync\BatchStrategies;

use App\Contracts\Sync\BatchStrategyInterface;
use App\Services\Connections\ConnectionManager;

class BatchStrategyFactory
{
    public function __construct(private ConnectionManager $connectionManager) {}

    public function make(string $connectionKey): BatchStrategyInterface
    {
        try {
            if (config("database.connections.{$connectionKey}")) {
                $driver = \Illuminate\Support\Facades\DB::connection($connectionKey)->getDriverName();
                return match ($driver) {
                    'sqlsrv' => new SqlServerBatchStrategy(),
                    default  => new MySqlBatchStrategy(),
                };
            }

            $cleanKey = preg_replace('/^dyn_/', '', $connectionKey);
            $connection = \App\Models\Connection::where('key', $cleanKey)->first();
            $type = $connection ? $connection->type : 'mysql';
            
            return match ($type) {
                'sqlsrv' => new SqlServerBatchStrategy(),
                'mysql' => new MySqlBatchStrategy(),
                default => new MySqlBatchStrategy(),
            };
        } catch (\Exception $e) {
            return new MySqlBatchStrategy();
        }
    }
}
