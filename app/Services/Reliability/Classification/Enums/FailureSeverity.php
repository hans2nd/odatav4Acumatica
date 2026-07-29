<?php

namespace App\Services\Reliability\Classification\Enums;

class FailureSeverity
{
    public const INFO = 'INFO';
    public const WARNING = 'WARNING';
    public const RECOVERABLE = 'RECOVERABLE';
    public const CRITICAL = 'CRITICAL';
    public const FATAL = 'FATAL';
}
