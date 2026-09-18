<?php

namespace App\Services\Scheduling;

class ScheduleResult
{
    /**
     * @param bool $wasTriggered Apakah eksekusi sinkronisasi berhasil dipantik
     * @param string $status Status penjadwalan (Misal: TRIGGERED, SKIPPED, FAILED)
     * @param ScheduleExecutionSummary $summary Rincian penolakan / pemicu
     * @param SchedulerMetrics $metrics Metrik evaluasi jadwal
     */
    public function __construct(
        public readonly bool $wasTriggered,
        public readonly string $status,
        public readonly ScheduleExecutionSummary $summary,
        public readonly SchedulerMetrics $metrics
    ) {}
}
