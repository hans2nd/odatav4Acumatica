<?php

namespace App\Services\Scheduling\Policies;

use App\Services\Scheduling\Contracts\SchedulePolicyInterface;
use App\Services\Scheduling\ScheduleDefinition;
use App\Services\Scheduling\ScheduleContext;
use App\Services\Scheduling\Enums\ScheduleType;
use DateTime;
use DateTimeZone;

class CronSchedulePolicy implements SchedulePolicyInterface
{
    public function supports(string $scheduleType): bool
    {
        return $scheduleType === ScheduleType::CRON;
    }

    public function isEligible(ScheduleDefinition $definition, ScheduleContext $context): bool
    {
        // Dalam implementasi nyata, di sini akan memanggil library (seperti mtdowling/cron-expression) 
        // untuk mengecek: CronExpression::factory($definition->expression)->isDue(new DateTime('now', $tz));
        // Karena ini purwarupa logika murni, dikembalikan true sebagai abstraksi.

        return true; 
    }
}
