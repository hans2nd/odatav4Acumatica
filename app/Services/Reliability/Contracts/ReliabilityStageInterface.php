<?php

namespace App\Services\Reliability\Contracts;

use App\Services\Reliability\Pipeline\ReliabilityPipelineExecutionContext;
use Closure;

interface ReliabilityStageInterface
{
    /**
     * Memproses konteks dan meneruskannya ke tahapan selanjutnya via Closure
     */
    public function handle(ReliabilityPipelineExecutionContext $context, Closure $next): ReliabilityPipelineExecutionContext;
}
