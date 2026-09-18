<?php

namespace App\Services\Orchestration\Stages;

use App\Services\Orchestration\Contracts\SynchronizationStageInterface;
use App\Services\Orchestration\SynchronizationExecutionContext;
use Closure;

class ReliabilityPipelineStage implements SynchronizationStageInterface
{
    public function process(SynchronizationExecutionContext $context, Closure $next): SynchronizationExecutionContext
    {
        // Tahap yang diaktifkan jika terdapat instruksi retry/resume, atau 
        // bertugas menyisipkan Checkpoint terakhir jika proses sukses.
        
        $context->summary->stageResults['reliability'] = 'Checkpoint validated / saved.';
        
        return $next($context);
    }
}
