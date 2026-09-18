<?php

namespace App\Services\Telemetry\Models;

class MetricsRequest
{
    /**
     * @param string $jobId ID Sinkronisasi yang target metriknya akan diolah
     * @param array<MetricSample> $rawSamples Array bahan baku sebelum agregasi
     * @param array<TelemetryEvent> $rawEvents Array kejadian yang direkam di fase sebelumnya
     */
    public function __construct(
        public readonly string $jobId,
        public readonly array $rawSamples = [],
        public readonly array $rawEvents = []
    ) {}
}
