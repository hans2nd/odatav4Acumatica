<?php

namespace App\Services\Orchestration;

class SynchronizationExecutionResult
{
    /**
     * @param string $status Status akhir sinkronisasi (Lihat SynchronizationStatus)
     * @param SynchronizationSummary $summary Ringkasan hasil per tahap
     * @param SynchronizationExecutionMetrics $metrics Metrik eksekusi (waktu, data, memori)
     * @param \Throwable|null $fatalError Eksepsi jika sinkronisasi hancur berantakan
     */
    public function __construct(
        public readonly string $status,
        public readonly SynchronizationSummary $summary,
        public readonly SynchronizationExecutionMetrics $metrics,
        public readonly ?\Throwable $fatalError = null
    ) {}
}
