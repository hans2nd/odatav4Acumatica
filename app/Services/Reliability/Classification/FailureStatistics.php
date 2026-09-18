<?php

namespace App\Services\Reliability\Classification;

class FailureStatistics
{
    public int $totalClassifications = 0;
    public int $totalRecognized = 0;
    public int $totalUnknown = 0;
    public float $durationSeconds = 0.0;
}
