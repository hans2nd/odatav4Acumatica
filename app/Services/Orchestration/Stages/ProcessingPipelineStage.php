<?php

namespace App\Services\Orchestration\Stages;

use App\Services\Orchestration\Contracts\SynchronizationStageInterface;
use App\Services\Orchestration\SynchronizationExecutionContext;
use Closure;

class ProcessingPipelineStage implements SynchronizationStageInterface
{
    public function process(SynchronizationExecutionContext $context, Closure $next): SynchronizationExecutionContext
    {
        // Mendelegasikan ke Processing Pipeline (Mengekstraksi data API, memvalidasi dan mengolahnya ke DTO)
        
        $context->summary->stageResults['processing'] = 'Data extracted and mapped.';
        
        // Simulasi update metrik (Di aslinya diubah berdasar result Pipeline)
        // $context->metrics->rowsProcessed += 100;
        
        return $next($context);
    }
}
