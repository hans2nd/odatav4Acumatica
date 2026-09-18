<?php

namespace App\Services\Monitoring\Models;

class MonitoringResult
{
    /**
     * @param string $jobId ID Sinkronisasi yang dievaluasi
     * @param PipelineHealthSummary $summary Rangkuman kesehatan keseluruhan (termasuk status terburuk)
     * @param MonitoringStatistics $statistics Statistik waktu evaluasi dll.
     */
    public function __construct(
        public readonly string $jobId,
        public readonly PipelineHealthSummary $summary,
        public readonly MonitoringStatistics $statistics
    ) {}
}
