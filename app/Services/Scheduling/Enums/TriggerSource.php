<?php

namespace App\Services\Scheduling\Enums;

class TriggerSource
{
    public const MANUAL = 'MANUAL';
    public const SCHEDULER = 'SCHEDULER';
    public const API = 'API';
    public const CLI = 'CLI';
    public const QUEUE = 'QUEUE';
    public const EVENT = 'EVENT';
}
