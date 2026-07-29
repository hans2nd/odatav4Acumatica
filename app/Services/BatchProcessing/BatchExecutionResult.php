<?php

namespace App\Services\BatchProcessing;

class BatchExecutionResult
{
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_FAILED = 'FAILED';

    /**
     * @param string $status
     * @param array<BatchResult> $batchResults
     * @param BatchExecutionStatistics $statistics
     */
    public function __construct(
        public readonly string $status,
        public readonly array $batchResults,
        public readonly BatchExecutionStatistics $statistics
    ) {}
}
