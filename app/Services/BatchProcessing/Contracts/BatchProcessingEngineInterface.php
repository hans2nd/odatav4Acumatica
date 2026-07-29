<?php

namespace App\Services\BatchProcessing\Contracts;

use App\Services\BatchPlanning\ExecutionPlan;
use App\Services\BatchProcessing\BatchExecutionContext;
use App\Services\BatchProcessing\BatchExecutionResult;

interface BatchProcessingEngineInterface
{
    /**
     * Mengeksekusi seluruh rencana pemrosesan batch berdasarkan ExecutionPlan yang disediakan.
     * 
     * @param ExecutionPlan $plan
     * @param BatchExecutionContext $context
     * @return BatchExecutionResult
     */
    public function execute(ExecutionPlan $plan, BatchExecutionContext $context): BatchExecutionResult;
}
