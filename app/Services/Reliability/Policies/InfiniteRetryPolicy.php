<?php

namespace App\Services\Reliability\Policies;

use App\Services\Reliability\Contracts\RetryPolicyInterface;
use App\Services\Reliability\RetryContext;

class InfiniteRetryPolicy implements RetryPolicyInterface
{
    public function calculateDelay(int $attempt, RetryContext $context): int
    {
        // Menggunakan fixed delay atau exponential backoff, namun dilindungi oleh safeguard
        return min($context->baseDelayMilliseconds, $context->maxDelayMilliseconds);
    }
}
