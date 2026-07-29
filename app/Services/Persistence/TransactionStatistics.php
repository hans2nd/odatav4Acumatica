<?php

namespace App\Services\Persistence;

class TransactionStatistics
{
    public float $durationSeconds = 0.0;
    public int $nestedLevels = 0;
    public int $savepointsCreated = 0;
    public bool $rolledBack = false;
}
