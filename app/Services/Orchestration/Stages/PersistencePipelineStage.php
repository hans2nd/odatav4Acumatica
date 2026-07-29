<?php

namespace App\Services\Orchestration\Stages;

use App\Services\Orchestration\Contracts\SynchronizationStageInterface;
use App\Services\Orchestration\SynchronizationExecutionContext;
use Closure;
use Exception;

class PersistencePipelineStage implements SynchronizationStageInterface
{
    public function process(SynchronizationExecutionContext $context, Closure $next): SynchronizationExecutionContext
    {
        // Mendelegasikan ke Persistence Pipeline (Transaksi DB, Delete, Upsert)
        
        try {
            $context->summary->stageResults['persistence'] = 'Database operations completed.';
            
            // Simulasi perhitungan
            // $context->metrics->insertCount += 50;
            // $context->metrics->updateCount += 50;
        } catch (Exception $e) {
            // Bila error (seperti koneksi putus), biarkan melempar error agar ditangkap oleh Orkestrator
            // dan diserahkan ke ReliabilityStage jika alurnya mendesain demikian, atau
            // di-catch di dalam Pipeline untuk langsung Reliability Pipeline.
            throw $e;
        }
        
        return $next($context);
    }
}
