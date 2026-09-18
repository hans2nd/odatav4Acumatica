<?php

namespace App\Services\Alerting\Models;

class AlertStatistics
{
    public int $rulesEvaluated = 0;
    public int $alertsFired = 0;
    public int $notificationsSent = 0;
    public int $notificationsFailed = 0;
    public float $evaluationDurationSeconds = 0.0;
}
