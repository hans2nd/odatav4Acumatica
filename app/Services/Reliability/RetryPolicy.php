<?php

namespace App\Services\Reliability;

class RetryPolicy
{
    public const NO_RETRY = 'NO_RETRY';
    public const FIXED = 'FIXED';
    public const LINEAR_BACKOFF = 'LINEAR_BACKOFF';
    public const EXPONENTIAL_BACKOFF = 'EXPONENTIAL_BACKOFF';
    public const EXPONENTIAL_BACKOFF_WITH_JITTER = 'EXPONENTIAL_BACKOFF_WITH_JITTER';
    public const INFINITE = 'INFINITE';
}
