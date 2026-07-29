<?php

namespace App\Services\Orchestration\Enums;

class SynchronizationStatus
{
    public const PENDING = 'PENDING';
    public const RUNNING = 'RUNNING';
    public const COMPLETED = 'COMPLETED';
    public const FAILED = 'FAILED';
    public const ABORTED = 'ABORTED';
    public const SKIPPED = 'SKIPPED';
}
