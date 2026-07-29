<?php

namespace App\Services\ProcessingPipeline\Stages;

use App\Services\ProcessingPipeline\Contracts\PipelineStageInterface;
use App\Services\ProcessingPipeline\PipelineContext;
use App\Services\ProcessingPipeline\PipelineExecutionContext;
use App\Services\ProcessingPipeline\PipelineMetrics;
use App\Services\BatchProcessing\Contracts\BatchProcessingEngineInterface;
use App\Services\BatchProcessing\BatchExecutionContext;

class BatchExecutionStage implements PipelineStageInterface
{
    public function __construct(
        private readonly BatchProcessingEngineInterface $batchEngine
    ) {}

    public function getName(): string
    {
        return 'BatchExecutionStage';
    }

    public function execute(mixed $payload, PipelineContext $context, PipelineExecutionContext $executionContext, PipelineMetrics $metrics): mixed
    {
        // Payload di-ekspektasikan berupa ExecutionPlan
        $batchContext = new BatchExecutionContext();
        
        // Mem-pass-through sinyal cancellation & executor callback dari Pipeline Execution Context
        if ($executionContext->isCancelled()) {
            $batchContext->requestCancellation();
        }
        
        // Jembatan eksekutor
        $batchContext->executorCallback = $executionContext->batchExecutorCallback;
        
        // Anda juga bisa menghubungkan event lifecycle di sini jika diperlukan
        
        $batchExecutionResult = $this->batchEngine->execute($payload, $batchContext);
        
        return $batchExecutionResult; // Hasil Final
    }
}
