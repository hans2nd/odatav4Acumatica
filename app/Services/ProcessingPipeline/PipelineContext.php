<?php

namespace App\Services\ProcessingPipeline;

class PipelineContext
{
    public function __construct(
        public readonly \App\Services\Mappers\MappingContext $mappingContext,
        public readonly \App\Services\SchemaValidators\ValidationProfile $validationProfile,
        public readonly \App\Services\ChangeDetection\ChangeDetectionContext $changeDetectionContext,
        public readonly \App\Services\BatchPlanning\ExecutionProfile $executionProfile
    ) {}
}
