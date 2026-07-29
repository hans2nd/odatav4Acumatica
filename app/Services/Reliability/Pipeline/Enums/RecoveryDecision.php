<?php

namespace App\Services\Reliability\Pipeline\Enums;

class RecoveryDecision
{
    public const RETRY = 'RETRY';
    public const RESUME = 'RESUME';
    public const SKIP = 'SKIP';
    public const ABORT = 'ABORT';
    public const MANUAL_INTERVENTION = 'MANUAL_INTERVENTION';
    public const ESCALATE = 'ESCALATE';
}
