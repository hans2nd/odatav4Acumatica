<?php

namespace App\Services\ProcessingPipeline\Stages;

use App\Services\ProcessingPipeline\Contracts\PipelineStageInterface;
use App\Services\ProcessingPipeline\PipelineContext;
use App\Services\ProcessingPipeline\PipelineExecutionContext;
use App\Services\ProcessingPipeline\PipelineMetrics;
use App\Services\SchemaValidators\Contracts\SchemaValidatorInterface;
use App\Services\SchemaValidators\ValidationContext;
use RuntimeException;

class ValidatorStage implements PipelineStageInterface
{
    public function __construct(
        private readonly SchemaValidatorInterface $validator
    ) {}

    public function getName(): string
    {
        return 'ValidatorStage';
    }

    public function execute(mixed $payload, PipelineContext $context, PipelineExecutionContext $executionContext, PipelineMetrics $metrics): mixed
    {
        // Payload di-ekspektasikan berupa NormalizedRecordCollection
        $valContext = new ValidationContext($payload, $context->validationProfile);
        
        $result = $this->validator->validate($valContext);
        
        $metrics->validationFailures = count($result->getErrors());

        if (!$result->isValid) {
            throw new RuntimeException("Terjadi ". count($result->getErrors()) ." kegagalan struktural data pada Schema Validation.");
        }
        
        return $payload; // Meloloskan Payload utuh karena validasi sukses
    }
}
