<?php

namespace App\Services\Persistence\Pipeline;

class PersistencePipelineMetrics
{
    public int $totalProcessed = 0;
    public int $totalInsertedOrUpdated = 0;
    public int $totalDeleted = 0;
    public int $totalSkipped = 0;
    public int $totalFailed = 0;
    
    public int $conflictCount = 0;
    
    public int $transactionCount = 0;
    public int $commitCount = 0;
    public int $rollbackCount = 0;
    
    public float $durationSeconds = 0.0;
    public float $memoryUsageMb = 0.0;
}
