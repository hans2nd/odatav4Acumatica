<?php

namespace App\Services\Alerting\Rules;

use App\Services\Alerting\Contracts\AlertRuleInterface;
use App\Services\Alerting\Enums\AlertSeverity;
use App\Services\Alerting\Enums\NotificationChannel;
use App\Services\Alerting\Models\AlertContext;
use App\Services\Alerting\Models\AlertDefinition;

class MemoryThresholdRule implements AlertRuleInterface
{
    public function evaluate(AlertContext $context): ?AlertDefinition
    {
        // Dalam implementasi nyata, kita membaca dari $context->metrics
        // Misalnya jika ada metrik 'peak_memory_usage' > policy->memoryThresholdMb
        // Untuk contoh ini, kita asumsikan null (RESOLVED) secara default.

        return null;
    }
}
