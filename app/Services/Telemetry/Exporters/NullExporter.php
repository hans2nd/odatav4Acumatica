<?php

namespace App\Services\Telemetry\Exporters;

use App\Services\Telemetry\Contracts\TelemetryExporterInterface;
use App\Services\Telemetry\Models\TelemetryBatch;

class NullExporter implements TelemetryExporterInterface
{
    public function export(TelemetryBatch $batch): void
    {
        // Exporter hampa. Tidak melakukan pengiriman fisik apa pun.
        // Di lingkungan nyata, bisa diganti PrometheusExporter / CloudWatchExporter.
    }
}
