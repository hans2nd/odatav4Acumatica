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
            $connection = \App\Models\Connection::where('key', $connectionKey)->first();
            $type = $connection ? $connection->type : 'mysql'; // Default fallback
            
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
