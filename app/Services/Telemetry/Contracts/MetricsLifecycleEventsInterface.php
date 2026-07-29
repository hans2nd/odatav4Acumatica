<?php

namespace App\Services\Telemetry\Contracts;

use App\Services\Telemetry\Models\MetricsRequest;
use App\Services\Telemetry\Models\MetricsContext;
use App\Services\Telemetry\Models\MetricsResult;
use Throwable;

interface MetricsLifecycleEventsInterface
{
    public function beforeMetricCollection(MetricsRequest $request, MetricsContext $context): void;
    public function afterMetricCollection(MetricsResult $result): void;
    public function beforeAggregation(MetricsRequest $request): void;
    public function afterAggregation(array $series): void;
    public function onMetricFailure(Throwable $exception): void;
    public function onMetricCompleted(MetricsResult $result): void;
}
