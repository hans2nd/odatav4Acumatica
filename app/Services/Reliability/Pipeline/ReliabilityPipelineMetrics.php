<?php

namespace App\Services\Reliability\Pipeline;

class ReliabilityPipelineMetrics
{
    public int $failureCount = 0;
    public int $retryCount = 0;
    public int $resumeCount = 0;
    public int $skippedCount = 0;
    public int $abortedCount = 0;
    public int $escalatedCount = 0;
    
    public int $successfulRecoveries = 0;
    
    public float $recoveryDurationSeconds = 0.0;
    public float $memoryUsageMb = 0.0;
}
