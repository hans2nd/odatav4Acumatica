<?php

namespace App\Services\ProcessingPipeline\Stages;

use App\Services\ProcessingPipeline\Contracts\PipelineStageInterface;
use App\Services\ProcessingPipeline\PipelineContext;
use App\Services\ProcessingPipeline\PipelineExecutionContext;
use App\Services\ProcessingPipeline\PipelineMetrics;
use App\Services\BatchPlanning\Contracts\BatchPlannerInterface;
use App\Services\BatchPlanning\ProcessingContext as BatchPlanningContext;

class BatchPlannerStage implements PipelineStageInterface
{
    public function __construct(
        private readonly BatchPlannerInterface $planner
    ) {}

    public function getName(): string
    {
        return 'BatchPlannerStage';
    }

    public function execute(mixed $payload, PipelineContext $context, PipelineExecutionContext $executionContext, PipelineMetrics $metrics): mixed
    {
        // Payload di-ekspektasikan berupa array of ChangeDetectionResult
        $batchPlanningContext = new BatchPlanningContext(
            $context->changeDetectionContext->snapshot ? 'EntityName_HasSnapshot' : 'EntityName_Dynamic', 
            $payload,
            $context->executionProfile
        );

        $executionPlan = $this->planner->plan($batchPlanningContext);
        
        $metrics->generatedBatches = count($executionPlan->batchPlans);

        return $executionPlan; // Mengembalikan ExecutionPlan
    }
}
