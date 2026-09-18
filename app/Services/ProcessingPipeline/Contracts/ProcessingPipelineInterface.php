<?php

namespace App\Services\ProcessingPipeline\Contracts;

use App\Services\ProcessingPipeline\PipelineContext;
use App\Services\ProcessingPipeline\PipelineExecutionContext;
use App\Services\ProcessingPipeline\PipelineExecutionResult;

interface ProcessingPipelineInterface
{
    /**
     * Memulai proses perakitan data end-to-end.
     * 
     * @param mixed $initialPayload
     * @param PipelineContext $context
     * @param PipelineExecutionContext $executionContext
     * @return PipelineExecutionResult
     */
    public function execute(mixed $initialPayload, PipelineContext $context, PipelineExecutionContext $executionContext): PipelineExecutionResult;
}
