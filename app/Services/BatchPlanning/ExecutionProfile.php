<?php

namespace App\Services\BatchPlanning;

class ExecutionProfile
{
    public function __construct(
        public readonly int $maxBatchSize = 100,
        public readonly int $maxMemoryBytes = 10485760, // 10MB default
        public readonly int $maxTransactionSize = 500
    ) {}
}
