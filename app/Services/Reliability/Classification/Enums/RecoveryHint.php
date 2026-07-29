<?php

namespace App\Services\Reliability\Classification\Enums;

class RecoveryHint
{
    public const RETRY = 'RETRY';
    public const RESUME = 'RESUME';
    public const SKIP = 'SKIP';
    public const ABORT = 'ABORT';
    public const MANUAL_INTERVENTION = 'MANUAL_INTERVENTION';
    public const ESCALATION = 'ESCALATION';
}
