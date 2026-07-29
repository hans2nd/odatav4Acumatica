<?php

namespace App\Services\Reliability;

class RetryStatistics
{
    public int $totalEvaluations = 0;
    public int $totalEligible = 0;
    public int $totalRejected = 0;
    public float $durationSeconds = 0.0;
}
