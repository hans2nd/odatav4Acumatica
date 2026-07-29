<?php

namespace App\Services\Monitoring\Rules;

use App\Services\Monitoring\Contracts\HealthRuleInterface;
use App\Services\Monitoring\Enums\HealthStatus;
use App\Services\Monitoring\Models\ExecutionHealth;
use App\Services\Monitoring\Models\ExecutionHistory;
use App\Services\Monitoring\Models\HealthPolicy;
use App\Services\Orchestration\Enums\SynchronizationStatus;

class RepeatedFailureRule implements HealthRuleInterface
{
    public function evaluate(ExecutionHistory $history, HealthPolicy $policy): ExecutionHealth
    {
        $failCount = 0;

        foreach ($history->recentStatuses as $status) {
            if ($status === SynchronizationStatus::FAILED || $status === SynchronizationStatus::ABORTED) {
                $failCount++;
            } else {
                // Break berantai jika ada sukses di antaranya
                break;
            }
        }

        if ($failCount >= $policy->maxRepeatedFailures) {
            return new ExecutionHealth(
                'RepeatedFailureRule',
                HealthStatus::CRITICAL,
                "Terdeteksi {$failCount} kegagalan berturut-turut (Batas: {$policy->maxRepeatedFailures})"
            );
        }

        if ($failCount > 0) {
            return new ExecutionHealth(
                'RepeatedFailureRule',
                HealthStatus::WARNING,
                "Terdeteksi {$failCount} kegagalan terbaru."
            );
        }

        return new ExecutionHealth('RepeatedFailureRule', HealthStatus::HEALTHY, "Tidak ada kegagalan beruntun.");
    }
}
