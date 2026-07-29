<?php

namespace App\Services\Persistence;

class ConflictStatistics
{
    public int $totalConflictsDetected = 0;
    public int $totalResolvedSource = 0;
    public int $totalResolvedDestination = 0;
    public int $totalSkipped = 0;
    public int $totalFailed = 0;
    public float $durationSeconds = 0.0;
}
