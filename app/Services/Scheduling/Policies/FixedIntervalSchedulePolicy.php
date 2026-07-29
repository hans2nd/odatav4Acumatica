<?php

namespace App\Services\Scheduling\Policies;

use App\Services\Scheduling\Contracts\SchedulePolicyInterface;
use App\Services\Scheduling\ScheduleDefinition;
use App\Services\Scheduling\ScheduleContext;
use App\Services\Scheduling\Enums\ScheduleType;

class FixedIntervalSchedulePolicy implements SchedulePolicyInterface
{
    public function supports(string $scheduleType): bool
    {
        return $scheduleType === ScheduleType::FIXED_INTERVAL;
    }

    public function isEligible(ScheduleDefinition $definition, ScheduleContext $context): bool
    {
        if (!$context->lastExecutionTimestamp) {
            return true; // Belum pernah jalan, maka layak
        }

        $lastExecutionTime = strtotime($context->lastExecutionTimestamp);
        $currentTime = time();
        $intervalSeconds = (int) $definition->expression;

        return ($currentTime - $lastExecutionTime) >= $intervalSeconds;
    }
}
