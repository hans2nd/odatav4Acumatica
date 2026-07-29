<?php

namespace App\Services\ProcessingPipeline;

class PipelineExecutionResult
{
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public function __construct(
        public readonly string $status,
        public readonly mixed $finalPayload,
        public readonly PipelineMetrics $metrics,
        public readonly ?\Throwable $error = null,
        public readonly ?string $failedStage = null
    ) {}
}
