<?php

namespace App\Services\Telemetry\Contracts;

use App\Services\Telemetry\Models\MetricsRequest;
use App\Services\Telemetry\Models\MetricsContext;
use App\Services\Telemetry\Models\MetricsResult;

interface MetricsEngineInterface
{
    public function addLifecycle(MetricsLifecycleEventsInterface $lifecycle): void;

    /**
     * Menerima permintaan yang berisi rawSamples, lalu diagregasi dan disebar ke exporter.
     */
    public function collect(MetricsRequest $request, MetricsContext $context): MetricsResult;
}
