<?php

namespace App\Services\Persistence\Contracts;

use App\Services\Persistence\Pipeline\PersistencePipelineContext;
use App\Services\Persistence\Pipeline\PersistencePipelineResult;
use App\Services\Persistence\Pipeline\PersistencePipelineExecutionContext;
use Throwable;

interface PersistenceLifecycleEventsInterface
{
    public function beforePersistence(PersistencePipelineContext $context): void;
    public function afterPersistence(PersistencePipelineResult $result, PersistencePipelineContext $context): void;
    public function beforeStage(string $stageName, PersistencePipelineExecutionContext $context): void;
    public function afterStage(string $stageName, PersistencePipelineExecutionContext $context): void;
    public function onStageFailure(string $stageName, Throwable $exception, PersistencePipelineExecutionContext $context): void;
}
