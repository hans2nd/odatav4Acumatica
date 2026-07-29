<?php

namespace App\Services\BatchProcessing;

use App\Services\BatchPlanning\BatchGroup;
use Throwable;

class BatchResult
{
    public function __construct(
        public readonly BatchGroup $group,
        public readonly bool $isSuccess,
        public readonly ?Throwable $error = null,
        public readonly int $processedCount = 0
    ) {}
}
