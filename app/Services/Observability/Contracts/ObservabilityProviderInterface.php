<?php

namespace App\Services\Observability\Contracts;

use App\Services\Observability\Models\ObservabilityRecord;

interface ObservabilityProviderInterface
{
    /**
     * Mempublikasikan rekaman jejak telemetri (misal ke Log file, Elastic, Jaeger, dsb)
     */
    public function publish(ObservabilityRecord $record): void;
}
