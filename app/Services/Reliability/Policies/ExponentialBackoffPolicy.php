<?php

namespace App\Services\Reliability\Policies;

use App\Services\Reliability\Contracts\RetryPolicyInterface;
use App\Services\Reliability\RetryContext;

class ExponentialBackoffPolicy implements RetryPolicyInterface
{
    public function calculateDelay(int $attempt, RetryContext $context): int
    {
        // Formula: Base * (Multiplier ^ Attempt)
        $delay = $context->baseDelayMilliseconds * pow($context->multiplier, $attempt - 1);
        
        return min((int) $delay, $context->maxDelayMilliseconds);
    }
}
