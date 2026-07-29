<?php

namespace App\Services\Alerting\Rules;

use App\Services\Alerting\Contracts\AlertRuleInterface;
use App\Services\Alerting\Enums\AlertSeverity;
use App\Services\Alerting\Enums\NotificationChannel;
use App\Services\Alerting\Models\AlertContext;
use App\Services\Alerting\Models\AlertDefinition;
use App\Services\Monitoring\Enums\HealthStatus;

class ExecutionFailureRule implements AlertRuleInterface
{
    public function evaluate(AlertContext $context): ?AlertDefinition
    {
        // Jika Pipeline Monitoring menyimpulkan CRITICAL atau FAILED
        $status = $context->monitoring->summary->overallStatus;
        if ($status === HealthStatus::CRITICAL || $status === HealthStatus::FAILED) {
            return new AlertDefinition(
                'EXEC_FAIL_01',
                AlertSeverity::CRITICAL,
                [NotificationChannel::LOG, NotificationChannel::SLACK],
                'Kegagalan Kritis Sinkronisasi'
            );
        }

        return null;
    }
}
