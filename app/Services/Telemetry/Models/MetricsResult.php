<?php

namespace App\Services\Telemetry\Models;

class MetricsResult
{
    /**
     * @param bool $success Apakah seluruh rentetan sampel berhasil diolah dan diekspor
     * @param int $exportedSeriesCount Jumlah MetricSeries yang sukses dihitung dan diekspor
     * @param MetricsStatistics $statistics Metrik kinerja mesin Telemetri ini sendiri
     */
    public function __construct(
        public readonly bool $success,
        public readonly int $exportedSeriesCount,
        public readonly MetricsStatistics $statistics
    ) {}
}
