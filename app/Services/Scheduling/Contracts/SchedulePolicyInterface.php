<?php

namespace App\Services\Scheduling\Contracts;

use App\Services\Scheduling\ScheduleDefinition;
use App\Services\Scheduling\ScheduleContext;

interface SchedulePolicyInterface
{
    /**
     * Mengembalikan true bila policy ini yang bertugas mengevaluasi ScheduleType tertentu
     */
    public function supports(string $scheduleType): bool;

    /**
     * Menghitung dan mengevaluasi apakah berdasarkan definisi dan konteks saat ini, jadwal layak dipicu
     */
    public function isEligible(ScheduleDefinition $definition, ScheduleContext $context): bool;
}
