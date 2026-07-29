<?php

namespace App\Services\Telemetry\Models;

class TelemetryBatch
{
    /**
     * @param array<MetricSeries> $series Array kumpulan seri agregat (Hitungan angka statis)
     * @param array<TelemetryEvent> $events Array daftar kronologi (Hitungan waktu kejadian)
     */
    public function __construct(
        public readonly array $series,
        public readonly array $events
    ) {}
}
