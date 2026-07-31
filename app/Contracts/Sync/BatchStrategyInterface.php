<?php

namespace App\Contracts\Sync;

interface BatchStrategyInterface
{
    /**
     * Determine the optimal chunk size based on the number of columns and the destination connection type.
     */
    public function calculateChunkSize(int $columnCount): int;

    /**
     * Executes the upsert operation for a given chunk.
     */
    public function executeUpsert(string $connectionName, string $tableName, array $chunk, string $uniqueKey, array $columnsToUpdate): int;

    /**
     * Executes the bulk insert operation for a given chunk.
     */
    public function executeInsert(string $connectionName, string $tableName, array $chunk): int;
}
