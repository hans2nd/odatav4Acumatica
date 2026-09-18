<?php

namespace App\Services\Alerting\Contracts;

use App\Services\Alerting\Models\AlertRequest;
use App\Services\Alerting\Models\AlertContext;
use App\Services\Alerting\Models\AlertResult;

interface AlertingEngineInterface
{
    public function addLifecycle(AlertingLifecycleEventsInterface $lifecycle): void;

    /**
     * Mengevaluasi potensi alarm berdasarkan konteks terbaru
     */
    public function evaluate(AlertRequest $request, AlertContext $context): AlertResult;
}
