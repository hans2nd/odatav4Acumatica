<?php

namespace App\Services\BatchPlanning;

class ExecutionPlan
{
    /**
     * @param array<BatchPlan> $batchPlans
     * @param int $estimatedRecordCount
     * @param int $estimatedMemoryUsageBytes
     * @param int $estimatedTransactionCount
     * @param int $estimatedExecutionCost
     * @param array $metadata
     */
    public function __construct(
        public readonly array $batchPlans,
        public readonly int $estimatedRecordCount,
        public readonly int $estimatedMemoryUsageBytes,
        public readonly int $estimatedTransactionCount,
        public readonly int $estimatedExecutionCost,
        public readonly array $metadata = []
    ) {}
}
