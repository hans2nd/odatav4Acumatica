<?php

namespace App\Services\Sync\BatchStrategies;

use App\Contracts\Sync\BatchStrategyInterface;
use Illuminate\Support\Facades\DB;

class MySqlBatchStrategy implements BatchStrategyInterface
{
    public function calculateChunkSize(int $columnCount): int
    {
        $maxParams = 25000;
        $calculated = intdiv($maxParams, max($columnCount, 1));
        $upperCap = $columnCount > 30 ? 500 : 1000;
        // Cap chunk size to ensure low memory footprint and prevent MySQL max_allowed_packet overflow on wide tables
        return max(100, min($upperCap, $calculated));
    }

    public function executeUpsert(string $connectionName, string $tableName, array $chunk, array|string $uniqueKey, array $columnsToUpdate): int
    {
        $connection = DB::connection($connectionName);

        try {
            $upsertKeys = is_string($uniqueKey) ? [$uniqueKey] : $uniqueKey;
            $affected = $connection->table($tableName)->upsert($chunk, $upsertKeys, $columnsToUpdate);
            
            return $affected;
        } catch (\PDOException $e) {
            \Illuminate\Support\Facades\Log::error("STEP 7: Execute SQL (Failed with PDOException)", [
                'Message' => $e->getMessage(),
                'SQLSTATE' => $e->errorInfo[0] ?? null,
                'Driver Error' => $e->errorInfo[1] ?? null,
            ]);
            throw $e;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("STEP 7: Execute SQL (Failed with Exception)", [
                'Message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function executeInsert(string $connectionName, string $tableName, array $chunk): int
    {
        $connection = DB::connection($connectionName);

        try {
            $affected = $connection->table($tableName)->insertOrIgnore($chunk);
            return $affected;
        } catch (\PDOException $e) {
            \Illuminate\Support\Facades\Log::error("STEP 7 (Insert): Execute SQL (Failed with PDOException)", [
                'Message' => $e->getMessage(),
                'SQLSTATE' => $e->errorInfo[0] ?? null,
                'Driver Error' => $e->errorInfo[1] ?? null,
            ]);
            throw $e;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("STEP 7 (Insert): Execute SQL (Failed with Exception)", [
                'Message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
