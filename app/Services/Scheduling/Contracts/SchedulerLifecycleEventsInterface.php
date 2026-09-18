<?php

namespace App\Services\Scheduling\Contracts;

use App\Services\Scheduling\ScheduleRequest;
use App\Services\Scheduling\ScheduleResult;

interface SchedulerLifecycleEventsInterface
{
    public function beforeSchedule(ScheduleRequest $request): void;
    public function afterSchedule(ScheduleResult $result, ScheduleRequest $request): void;
    public function beforeExecution(ScheduleRequest $request): void;
    public function afterExecution(ScheduleRequest $request): void;
    public function onExecutionSkipped(ScheduleResult $result): void;
    public function onExecutionCompleted(ScheduleResult $result): void;
}
