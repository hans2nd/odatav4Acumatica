<?php

namespace App\Services\ProcessingPipeline;

use App\Services\ProcessingPipeline\Contracts\ProcessingPipelineInterface;
use App\Services\ProcessingPipeline\Stages\PipelineStageRegistry;
use Throwable;

class ProcessingPipeline implements ProcessingPipelineInterface
{
    public function __construct(
        private readonly PipelineStageRegistry $registry
    ) {}

    public function execute(mixed $initialPayload, PipelineContext $context, PipelineExecutionContext $executionContext): PipelineExecutionResult
    {
        $metrics = new PipelineMetrics();
        $startTime = microtime(true);
        $currentPayload = $initialPayload;
        $status = PipelineExecutionResult::STATUS_COMPLETED;
        $finalError = null;
        $failedStage = null;

        if (is_callable($executionContext->onBeforePipeline)) {
            call_user_func($executionContext->onBeforePipeline);
        }

        foreach ($this->registry->getStages() as $stage) {
            $stageName = $stage->getName();
            $stageStartTime = microtime(true);

            if (is_callable($executionContext->onBeforeStage)) {
                call_user_func($executionContext->onBeforeStage, $stageName);
            }

            try {
                // Eksekusi Estafet
                $currentPayload = $stage->execute($currentPayload, $context, $executionContext, $metrics);
                
                if (is_callable($executionContext->onAfterStage)) {
                    call_user_func($executionContext->onAfterStage, $stageName, $currentPayload);
                }

                $metrics->stageDurations[$stageName] = round(microtime(true) - $stageStartTime, 4);
                
            } catch (Throwable $e) {
                // Fail-Fast Terpicu
                $status = PipelineExecutionResult::STATUS_FAILED;
                $finalError = $e;
                $failedStage = $stageName;
                
                $metrics->stageDurations[$stageName] = round(microtime(true) - $stageStartTime, 4);

                if (is_callable($executionContext->onStageFailure)) {
                    call_user_func($executionContext->onStageFailure, $stageName, $e);
                }
                
                // Break the loop on Unrecoverable failure
                break;
            }
        }

        $metrics->executionDuration = round(microtime(true) - $startTime, 4);

        if ($executionContext->isCancelled()) {
            $status = PipelineExecutionResult::STATUS_CANCELLED;
        }

        $result = new PipelineExecutionResult(
            $status,
            $currentPayload, // Jika fail di tengah, ini berisi data dari stage terakhir yang sukses
            $metrics,
            $finalError,
            $failedStage
        );

        if (is_callable($executionContext->onPipelineCompleted)) {
            call_user_func($executionContext->onPipelineCompleted, $result);
        }

        return $result;
    }
}
