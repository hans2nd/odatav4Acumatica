<?php

namespace App\Services\Scheduling\Enums;

class ScheduleType
{
    public const ONE_TIME = 'ONE_TIME';
    public const FIXED_INTERVAL = 'FIXED_INTERVAL';
    public const CRON = 'CRON';
    public const DAILY = 'DAILY';
    public const WEEKLY = 'WEEKLY';
    public const MONTHLY = 'MONTHLY';
    public const CUSTOM = 'CUSTOM';
}
