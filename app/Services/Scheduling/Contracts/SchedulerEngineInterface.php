<?php

namespace App\Services\Scheduling\Contracts;

use App\Services\Scheduling\ScheduleRequest;
use App\Services\Scheduling\ScheduleContext;
use App\Services\Scheduling\ScheduleResult;

interface SchedulerEngineInterface
{
    public function addLifecycle(SchedulerLifecycleEventsInterface $lifecycle): void;
    
    /**
     * Memproses evaluasi penjadwalan dan memicu Orchestrator jika layak
     */
    public function schedule(ScheduleRequest $request, ScheduleContext $context): ScheduleResult;
}
