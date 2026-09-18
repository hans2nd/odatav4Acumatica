<?php

namespace App\Services\BatchPlanning;

class BatchGroup
{
    /**
     * @param string $operation INSERT, UPDATE, DELETE
     * @param array<\App\Services\Mappers\NormalizedRecord> $records
     * @param int $estimatedCost Perkiraan biaya komputasi/memori
     */
    public function __construct(
        public readonly string $operation,
        public readonly array $records,
        public readonly int $estimatedCost = 0
    ) {}
}
