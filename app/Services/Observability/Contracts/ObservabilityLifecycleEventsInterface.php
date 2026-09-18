<?php

namespace App\Services\Observability\Contracts;

use App\Services\Observability\Models\CorrelationId;
use App\Services\Observability\Models\ObservabilityContext;
use App\Services\Observability\Models\ExecutionLog;
use App\Services\Observability\Models\ExecutionEvent;
use App\Services\Observability\Models\ExecutionTrace;
use App\Services\Observability\Models\ExecutionSpan;
use App\Services\Observability\Models\ObservabilityResult;
use Throwable;

interface ObservabilityLifecycleEventsInterface
{
    public function beforeObservation(ObservabilityContext $context): void;
    public function afterObservation(ObservabilityContext $context): void;
    public function beforeRecording(CorrelationId $correlationId, ExecutionTrace $trace): void;
    public function afterRecording(CorrelationId $correlationId, ObservabilityResult $result): void;
    public function onObservationFailure(Throwable $exception): void;
    public function onObservationCompleted(ObservabilityResult $result): void;
}
