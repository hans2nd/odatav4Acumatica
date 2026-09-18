<?php

namespace App\Services\BatchProcessing;

class BatchExecutionStatistics
{
    public int $totalBatches = 0;
    public int $successfulBatches = 0;
    public int $failedBatches = 0;
    public int $totalRecordsProcessed = 0;
    public float $durationSeconds = 0.0;
}
