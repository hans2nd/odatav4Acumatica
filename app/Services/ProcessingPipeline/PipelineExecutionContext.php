<?php

namespace App\Services\ProcessingPipeline;

use Closure;

class PipelineExecutionContext
{
    private bool $isCancelled = false;

    /** @var Closure|null */
    public ?Closure $onBeforePipeline = null;

    /** @var Closure|null */
    public ?Closure $onAfterPipeline = null;

    /** @var Closure|null fn(string $stageName) */
    public ?Closure $onBeforeStage = null;

    /** @var Closure|null fn(string $stageName, mixed $result) */
    public ?Closure $onAfterStage = null;

    /** @var Closure|null fn(string $stageName, \Throwable $error) */
    public ?Closure $onStageFailure = null;

    /** @var Closure|null fn(PipelineExecutionResult $result) */
    public ?Closure $onPipelineCompleted = null;
    
    /**
     * Callback wajib yang dipasok ke mesin eksekusi batch
     * @var Closure|null fn(BatchGroup $group): int
     */
    public ?Closure $batchExecutorCallback = null;

    public function requestCancellation(): void
    {
        $this->isCancelled = true;
    }

    public function isCancelled(): bool
    {
        return $this->isCancelled;
    }
}
