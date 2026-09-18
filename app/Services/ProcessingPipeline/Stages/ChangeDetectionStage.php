<?php

namespace App\Services\ProcessingPipeline\Stages;

use App\Services\ProcessingPipeline\Contracts\PipelineStageInterface;
use App\Services\ProcessingPipeline\PipelineContext;
use App\Services\ProcessingPipeline\PipelineExecutionContext;
use App\Services\ProcessingPipeline\PipelineMetrics;
use App\Services\ChangeDetection\Contracts\ChangeDetectionEngineInterface;

class ChangeDetectionStage implements PipelineStageInterface
{
    public function __construct(
        private readonly ChangeDetectionEngineInterface $changeDetectionEngine
    ) {}

    public function getName(): string
    {
        return 'ChangeDetectionStage';
    }

    public function execute(mixed $payload, PipelineContext $context, PipelineExecutionContext $executionContext, PipelineMetrics $metrics): mixed
    {
        // Payload di-ekspektasikan berupa NormalizedRecordCollection
        $changeResults = [];

        foreach ($payload as $record) {
            $changeResult = $this->changeDetectionEngine->detect($record, $context->changeDetectionContext);
            $changeResults[] = $changeResult;
            
            if ($changeResult->operation->operation !== \App\Services\ChangeDetection\ChangeOperation::UNCHANGED) {
                $metrics->detectedChanges++;
            } else {
                $metrics->skippedRecords++;
            }
        }

        return $changeResults; // Mengembalikan array of ChangeDetectionResult
    }
}
