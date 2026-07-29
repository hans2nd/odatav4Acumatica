<?php

namespace App\Services\Persistence;

class PersistenceResponse
{
    /**
     * @param int $totalAffectedRows
     * @param array<PersistenceOperationResult> $operationResults
     */
    public function __construct(
        public readonly int $totalAffectedRows,
        public readonly array $operationResults = []
    ) {}
}
