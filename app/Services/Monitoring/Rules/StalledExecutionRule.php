<?php

namespace App\Services\Monitoring\Rules;

use App\Services\Monitoring\Contracts\HealthRuleInterface;
use App\Services\Monitoring\Enums\HealthStatus;
use App\Services\Monitoring\Models\ExecutionHealth;
use App\Services\Monitoring\Models\ExecutionHistory;
use App\Services\Monitoring\Models\HealthPolicy;

class StalledExecutionRule implements HealthRuleInterface
{
    public function evaluate(ExecutionHistory $history, HealthPolicy $policy): ExecutionHealth
    {
        if (!$history->isCurrentlyRunning || $history->currentRunningDurationSeconds === null) {
            return new ExecutionHealth('StalledExecutionRule', HealthStatus::HEALTHY, "Tidak ada eksekusi macet.");
        }

        if ($history->currentRunningDurationSeconds > $policy->stalledThresholdSeconds) {
            return new ExecutionHealth(
                'StalledExecutionRule',
                HealthStatus::DEGRADED,
                "Eksekusi berjalan lambat selama {$history->currentRunningDurationSeconds}s (Batas: {$policy->stalledThresholdSeconds}s)"
            );
        }

        return new ExecutionHealth('StalledExecutionRule', HealthStatus::HEALTHY, "Eksekusi berjalan normal.");
    }
}
