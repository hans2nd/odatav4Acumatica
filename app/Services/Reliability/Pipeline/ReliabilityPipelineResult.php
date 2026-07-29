<?php

namespace App\Services\Reliability\Pipeline;

class ReliabilityPipelineResult
{
    /**
     * @param bool $isResolved Apakah pipeline berhasil merumuskan keputusan solusi pemulihan
     * @param RecoveryExecutionSummary $summary Rangkuman instruksi perbaikan
     * @param ReliabilityPipelineMetrics $metrics Metrik eksekusi
     * @param \Throwable|null $error Kesalahan saat memproses pipeline
     */
    public function __construct(
        public readonly bool $isResolved,
        public readonly RecoveryExecutionSummary $summary,
        public readonly ReliabilityPipelineMetrics $metrics,
        public readonly ?\Throwable $error = null
    ) {}
}
