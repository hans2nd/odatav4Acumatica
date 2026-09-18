<?php

namespace App\Services\Persistence\Pipeline;

class PersistencePipelineResult
{
    public function __construct(
        public readonly bool $isSuccess,
        public readonly PersistencePipelineMetrics $metrics,
        public readonly ?\Throwable $error = null
    ) {}
}
