<?php

namespace App\Services\Monitoring\Models;

class PipelineHealthSummary
{
    /**
     * @param string $overallStatus Status kesehatan agregat (Misal: CRITICAL, HEALTHY)
     * @param array<ExecutionHealth> $details Detail dari seluruh aturan yang dievaluasi
     */
    public function __construct(
        public readonly string $overallStatus,
        public readonly array $details
    ) {}
}
