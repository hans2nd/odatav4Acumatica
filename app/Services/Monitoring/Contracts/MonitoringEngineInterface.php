<?php

namespace App\Services\Monitoring\Contracts;

use App\Services\Monitoring\Models\MonitoringRequest;
use App\Services\Monitoring\Models\MonitoringContext;
use App\Services\Monitoring\Models\MonitoringResult;

interface MonitoringEngineInterface
{
    public function addLifecycle(MonitoringLifecycleEventsInterface $lifecycle): void;

    /**
     * Mengevaluasi permintaan diagnostik
     */
    public function evaluate(MonitoringRequest $request, MonitoringContext $context): MonitoringResult;
}
