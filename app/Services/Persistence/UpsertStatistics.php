<?php

namespace App\Services\Persistence;

class UpsertStatistics
{
    public int $totalProcessed = 0;
    public int $totalInsertedOrUpdated = 0;
    public int $totalFailed = 0;
    public float $durationSeconds = 0.0;
}
