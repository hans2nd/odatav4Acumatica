<?php

namespace App\Services\Scheduling\Policies;

use App\Services\Scheduling\Contracts\SchedulePolicyInterface;
use App\Services\Scheduling\ScheduleDefinition;
use App\Services\Scheduling\ScheduleContext;

class ManualTriggerPolicy implements SchedulePolicyInterface
{
    public function supports(string $scheduleType): bool
    {
        // ManualTrigger tidak bergantung tipe jadwal statis, ia akan mengambil alih jika TriggerSource == MANUAL
        // Namun fungsi supports ini hanya sebagai fallback jika di dalam registry ingin didaftarkan.
        return false;
    }

    public function isEligible(ScheduleDefinition $definition, ScheduleContext $context): bool
    {
        // Pemicu manual selalu layak untuk dijalankan
        return true;
    }
}
