<?php

namespace App\Services\Orchestration\Contracts;

use App\Services\Orchestration\SynchronizationExecutionContext;
use App\Services\Orchestration\SynchronizationExecutionResult;
use Throwable;

interface SynchronizationLifecycleEventsInterface
{
    public function beforeSynchronization(SynchronizationExecutionContext $context): void;
    public function afterSynchronization(SynchronizationExecutionResult $result, SynchronizationExecutionContext $context): void;
    public function beforeStage(string $stageName, SynchronizationExecutionContext $context): void;
    public function afterStage(string $stageName, SynchronizationExecutionContext $context): void;
    public function onStageFailure(string $stageName, Throwable $exception, SynchronizationExecutionContext $context): void;
    public function onSynchronizationCompleted(SynchronizationExecutionResult $result): void;
}
