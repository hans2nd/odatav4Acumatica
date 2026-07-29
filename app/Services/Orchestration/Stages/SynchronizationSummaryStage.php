<?php

namespace App\Services\Orchestration\Stages;

use App\Services\Orchestration\Contracts\SynchronizationStageInterface;
use App\Services\Orchestration\SynchronizationExecutionContext;
use Closure;

class SynchronizationSummaryStage implements SynchronizationStageInterface
{
    public function process(SynchronizationExecutionContext $context, Closure $next): SynchronizationExecutionContext
    {
        // Tahap final untuk mengkompilasi semua log, menyusun kata akhir, 
        // serta memicu notifikasi jika dirancang di tahap-tahap awal.
        
        $context->summary->finalMessage = "Sinkronisasi entitas {$context->request->entityName} berhasil diproses tanpa hambatan berarti.";
        
        return $next($context);
    }
}
