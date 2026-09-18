<?php

namespace App\Services\Reliability\Policies;

use App\Services\Reliability\Contracts\RetryPolicyInterface;
use App\Services\Reliability\RetryContext;

class NoRetryPolicy implements RetryPolicyInterface
{
    public function calculateDelay(int $attempt, RetryContext $context): int
    {
        return 0; // Tidak ada penundaan, mesin langsung menghentikan karena status NO_RETRY disaring di Evaluator
    }
}
