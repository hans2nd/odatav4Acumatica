<?php

namespace App\Services\Observability;

class ObservabilityStatistics
{
    public int $totalTraces = 0;
    public int $totalSpans = 0;
    public int $totalLogs = 0;
    public int $totalEvents = 0;
    public int $failedPublish = 0;
}
