<?php

namespace App\Services\Telemetry\Contracts;

use App\Services\Telemetry\Models\TelemetryBatch;

interface TelemetryExporterInterface
{
    /**
     * Mengekspor rangkaian telemetri (series & event) ke sistem pihak ketiga.
     * 
     * @param TelemetryBatch $batch
     * @return void
     */
    public function export(TelemetryBatch $batch): void;
}
