<?php

namespace App\Services\Telemetry\Models;

class MetricsStatistics
{
    public int $totalRawSamplesProcessed = 0;
    public int $totalSeriesGenerated = 0;
    public float $aggregationDurationSeconds = 0.0;
}
