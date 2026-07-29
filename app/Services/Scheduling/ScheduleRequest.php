<?php

namespace App\Services\Scheduling;

class ScheduleRequest
{
    /**
     * @param string $jobId ID sinkronisasi target
     * @param string $triggerSource Sumber pemicu (Lihat TriggerSource)
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $triggerSource
    ) {}
}
