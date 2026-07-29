<?php

namespace App\Services\Reliability\Policies;

use App\Services\Reliability\Contracts\RetryPolicyInterface;
use App\Services\Reliability\RetryContext;

class LinearBackoffPolicy implements RetryPolicyInterface
{
    public function calculateDelay(int $attempt, RetryContext $context): int
    {
        $delay = $context->baseDelayMilliseconds * $attempt;
        return min($delay, $context->maxDelayMilliseconds);
    }
}
