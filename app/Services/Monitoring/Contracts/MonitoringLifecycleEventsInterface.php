<?php

namespace App\Services\Monitoring\Contracts;

use App\Services\Monitoring\Models\MonitoringRequest;
use App\Services\Monitoring\Models\MonitoringContext;
use App\Services\Monitoring\Models\MonitoringResult;
use Throwable;

interface MonitoringLifecycleEventsInterface
{
    public function beforeMonitoring(MonitoringRequest $request, MonitoringContext $context): void;
    public function afterMonitoring(MonitoringResult $result): void;
    public function beforeEvaluation(MonitoringContext $context): void;
    public function afterEvaluation(MonitoringResult $result): void;
    public function onMonitoringFailure(Throwable $exception): void;
    public function onMonitoringCompleted(MonitoringResult $result): void;
}
