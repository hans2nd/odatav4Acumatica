<?php

namespace App\Services\Reliability\Contracts;

use App\Services\Reliability\Pipeline\ReliabilityPipelineContext;
use App\Services\Reliability\Pipeline\ReliabilityPipelineResult;

interface ReliabilityPipelineInterface
{
    public function addLifecycle(ReliabilityLifecycleEventsInterface $lifecycle): void;
    
    /**
     * Memulai pemrosesan orkestrasi pemulihan
     */
    public function process(ReliabilityPipelineContext $context): ReliabilityPipelineResult;
}
