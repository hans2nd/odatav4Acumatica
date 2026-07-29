<?php

namespace App\Services\Orchestration\Stages;

use App\Services\Orchestration\Contracts\SynchronizationStageInterface;
use App\Services\Orchestration\SynchronizationExecutionContext;
use Closure;

class IncrementalCoordinationStage implements SynchronizationStageInterface
{
    public function process(SynchronizationExecutionContext $context, Closure $next): SynchronizationExecutionContext
    {
        // Berkoordinasi dengan mesin Incremental untuk menyiapkan SyncState
        // (Misalnya mengambil LastSyncDate, menyusun filter Delta)
        
        $context->summary->stageResults['incremental'] = 'Prepared state for entity ' . $context->request->entityName;
        
        return $next($context);
    }
}
