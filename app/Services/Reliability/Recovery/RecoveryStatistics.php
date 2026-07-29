<?php

namespace App\Services\Reliability\Recovery;

class RecoveryStatistics
{
    public int $checkpointsEvaluated = 0;
    public int $checkpointsRejected = 0;
    public float $durationSeconds = 0.0;
}
