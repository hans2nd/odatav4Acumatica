<?php

namespace App\Services\Reliability\Policies;

use App\Services\Reliability\Contracts\RetryPolicyInterface;
use App\Services\Reliability\RetryContext;

class ExponentialBackoffWithJitterPolicy implements RetryPolicyInterface
{
    public function calculateDelay(int $attempt, RetryContext $context): int
    {
        // Hitung batas backoff dasar
        $baseBackoff = $context->baseDelayMilliseconds * pow($context->multiplier, $attempt - 1);
        $cappedBackoff = min((int) $baseBackoff, $context->maxDelayMilliseconds);
        
        // Jitter: Memberi sedikit nilai acak untuk menghindari tabrakan jam yang sama di server terdistribusi
        // Hitung angka acak antara 50% sampai 100% dari cappedBackoff
        $minJitter = $cappedBackoff / 2;
        $delay = random_int((int) $minJitter, $cappedBackoff);
        
        return $delay;
    }
}
