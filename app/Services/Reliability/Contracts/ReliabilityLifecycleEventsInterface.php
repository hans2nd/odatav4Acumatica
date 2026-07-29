<?php

namespace App\Services\Reliability\Contracts;

use App\Services\Reliability\Pipeline\ReliabilityPipelineContext;
use App\Services\Reliability\Pipeline\ReliabilityPipelineResult;
use App\Services\Reliability\Pipeline\ReliabilityPipelineExecutionContext;
use Throwable;

interface ReliabilityLifecycleEventsInterface
{
    public function beforeRecovery(ReliabilityPipelineContext $context): void;
    public function afterRecovery(ReliabilityPipelineResult $result, ReliabilityPipelineContext $context): void;
    public function beforeStage(string $stageName, ReliabilityPipelineExecutionContext $context): void;
    public function afterStage(string $stageName, ReliabilityPipelineExecutionContext $context): void;
    public function onStageFailure(string $stageName, Throwable $exception, ReliabilityPipelineExecutionContext $context): void;
}
