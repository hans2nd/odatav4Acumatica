<?php

namespace App\Services\Orchestration;

class SynchronizationExecutionMetrics
{
    public float $executionDurationSeconds = 0.0;
    
    public int $entitiesProcessed = 0;
    public int $rowsProcessed = 0;
    
    public int $insertCount = 0;
    public int $updateCount = 0;
    public int $deleteCount = 0;
    
    public int $retryCount = 0;
    public int $resumeCount = 0;
    public int $failureCount = 0;
    
    public float $memoryUsageMb = 0.0;
}
