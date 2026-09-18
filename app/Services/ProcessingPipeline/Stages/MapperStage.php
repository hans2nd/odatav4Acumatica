<?php

namespace App\Services\ProcessingPipeline\Stages;

use App\Services\ProcessingPipeline\Contracts\PipelineStageInterface;
use App\Services\ProcessingPipeline\PipelineContext;
use App\Services\ProcessingPipeline\PipelineExecutionContext;
use App\Services\ProcessingPipeline\PipelineMetrics;
use App\Services\Mappers\Contracts\DataMapperInterface;

class MapperStage implements PipelineStageInterface
{
    public function __construct(
        private readonly DataMapperInterface $mapper
    ) {}

    public function getName(): string
    {
        return 'MapperStage';
    }

    public function execute(mixed $payload, PipelineContext $context, PipelineExecutionContext $executionContext, PipelineMetrics $metrics): mixed
    {
        // Parameter payload diekspektasikan sebagai array/iterator sumber data mentah
        $mappingResult = $this->mapper->map($payload, $context->mappingContext);
        
        $metrics->processedRecords = count($mappingResult->records);
        
        return $mappingResult->records; // Melempar NormalizedRecordCollection
    }
}
