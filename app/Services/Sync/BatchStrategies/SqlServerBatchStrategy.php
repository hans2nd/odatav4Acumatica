<?php

namespace App\Services\Sync\BatchStrategies;

use App\Contracts\Sync\BatchStrategyInterface;
use Illuminate\Support\Facades\DB;

class SqlServerBatchStrategy implements BatchStrategyInterface
{
    public function calculateChunkSize(int $columnCount): int
    {
        $maxParams = 2000;
        $calculated = intdiv($maxParams, max($columnCount, 1));
        return max(1, min(1000, $calculated));
    }

    public function executeUpsert(string $connectionName, string $tableName, array $chunk, array|string $uniqueKey, array $columnsToUpdate): int
    {
        \Illuminate\Support\Facades\Log::info("STEP 4: Entering SqlServerBatchStrategy::executeUpsert() for {$tableName} with " . count($chunk) . " rows");

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
        \Illuminate\Support\Facades\Log::info("STEP 4 (Insert): Entering SqlServerBatchStrategy::executeInsert() for {$tableName} with " . count($chunk) . " rows");

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
