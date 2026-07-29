<?php

namespace App\Services\Scheduling;

class SchedulerMetrics
{
    public int $scheduledExecutions = 0;
    public int $executedJobs = 0;
    public int $skippedJobs = 0;
    public int $failedScheduling = 0;
    
    public ?float $averageExecutionIntervalSeconds = null;
    
    public ?string $lastExecutionTimestamp = null;
    public ?string $nextExecutionTimestamp = null;
    
    public float $executionDurationSeconds = 0.0;
}
