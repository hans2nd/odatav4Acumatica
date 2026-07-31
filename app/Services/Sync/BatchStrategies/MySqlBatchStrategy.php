<?php

namespace App\Services\Sync\BatchStrategies;

use App\Contracts\Sync\BatchStrategyInterface;
use Illuminate\Support\Facades\DB;

class MySqlBatchStrategy implements BatchStrategyInterface
{
    public function calculateChunkSize(int $columnCount): int
    {
        $maxParams = 60000;
        return max(intdiv($maxParams, max($columnCount, 1)), 1);
    }

    public function executeUpsert(string $connectionName, string $tableName, array $chunk, string $uniqueKey, array $columnsToUpdate): int
    {
        \Illuminate\Support\Facades\Log::info("STEP 4: Entering MySqlBatchStrategy::executeUpsert()");
        \Illuminate\Support\Facades\Log::info("STEP 5: Upsert Parameters", [
            'Destination Connection' => $connectionName,
            'Destination Table' => $tableName,
            'Primary Key' => $uniqueKey,
            'Update Columns' => $columnsToUpdate,
            'Rows' => count($chunk),
        ]);

        $connection = DB::connection($connectionName);
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        $countBefore = $connection->table($tableName)->count();
        \Illuminate\Support\Facades\Log::info("STEP 8: SELECT COUNT(*) before upsert: {$countBefore}");

        try {
            $affected = $connection->table($tableName)->upsert($chunk, [$uniqueKey], $columnsToUpdate);
            
            $queries = $connection->getQueryLog();
            $lastQuery = end($queries);
            
            \Illuminate\Support\Facades\Log::info("STEP 6: Generated SQL", [
                'SQL' => $lastQuery['query'] ?? 'No query captured',
                'Bindings' => $lastQuery['bindings'] ?? []
            ]);
            
            \Illuminate\Support\Facades\Log::info("STEP 7: Execute SQL (Success)", [
                'Affected Rows' => $affected,
            ]);

            $countAfter = $connection->table($tableName)->count();
            \Illuminate\Support\Facades\Log::info("STEP 8: SELECT COUNT(*) after upsert: {$countAfter}");

            if ($countBefore === $countAfter && $affected === 0) {
                \Illuminate\Support\Facades\Log::error("STEP 9: Row count did NOT change. Halting for investigation.");
                throw new \Exception("Synchronization halted: executeUpsert completed but no rows were inserted or affected in {$tableName}.");
            }

            return $affected;
        } catch (\PDOException $e) {
            $queries = $connection->getQueryLog();
            $lastQuery = end($queries);
            
            \Illuminate\Support\Facades\Log::error("STEP 7: Execute SQL (Failed with PDOException)", [
                'SQL' => $lastQuery['query'] ?? 'No query captured',
                'Bindings' => $lastQuery['bindings'] ?? [],
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
        \Illuminate\Support\Facades\Log::info("STEP 4 (Insert): Entering MySqlBatchStrategy::executeInsert()");
        \Illuminate\Support\Facades\Log::info("STEP 5 (Insert): Insert Parameters", [
            'Destination Connection' => $connectionName,
            'Destination Table' => $tableName,
            'Rows' => count($chunk),
        ]);

        $connection = DB::connection($connectionName);
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        $countBefore = $connection->table($tableName)->count();
        \Illuminate\Support\Facades\Log::info("STEP 8 (Insert): SELECT COUNT(*) before insert: {$countBefore}");

        try {
            $affected = $connection->table($tableName)->insert($chunk) ? count($chunk) : 0;
            
            $queries = $connection->getQueryLog();
            $lastQuery = end($queries);
            
            \Illuminate\Support\Facades\Log::info("STEP 6 (Insert): Generated SQL", [
                'SQL' => $lastQuery['query'] ?? 'No query captured',
                'Bindings' => $lastQuery['bindings'] ?? []
            ]);
            
            \Illuminate\Support\Facades\Log::info("STEP 7 (Insert): Execute SQL (Success)", [
                'Affected Rows' => $affected,
            ]);

            $countAfter = $connection->table($tableName)->count();
            \Illuminate\Support\Facades\Log::info("STEP 8 (Insert): SELECT COUNT(*) after insert: {$countAfter}");

            if ($countBefore === $countAfter && $affected === 0) {
                \Illuminate\Support\Facades\Log::error("STEP 9 (Insert): Row count did NOT change. Halting for investigation.");
                throw new \Exception("Synchronization halted: executeInsert completed but no rows were inserted in {$tableName}.");
            }

            return $affected;
        } catch (\PDOException $e) {
            $queries = $connection->getQueryLog();
            $lastQuery = end($queries);
            
            \Illuminate\Support\Facades\Log::error("STEP 7 (Insert): Execute SQL (Failed with PDOException)", [
                'SQL' => $lastQuery['query'] ?? 'No query captured',
                'Bindings' => $lastQuery['bindings'] ?? [],
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
