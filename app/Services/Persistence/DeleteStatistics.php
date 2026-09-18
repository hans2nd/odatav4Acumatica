<?php

namespace App\Services\Persistence;

class DeleteStatistics
{
    public int $totalProcessed = 0;
    public int $totalDeleted = 0;
    public int $totalIgnored = 0;
    public int $totalFailed = 0;
    public float $durationSeconds = 0.0;
}
