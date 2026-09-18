<?php

namespace App\Services\Reliability;

class RetryContext
{
    /**
     * @param string $policy Jenis kebijakan (Lihat App\Services\Reliability\RetryPolicy)
     * @param int $maxAttempts Batas maksimal mencoba ulang (Default: 3)
     * @param int $baseDelayMilliseconds Jeda waktu dasar (Default: 1000ms)
     * @param int $maxDelayMilliseconds Batas maksimal jeda agar tidak terlalu lama (Default: 30000ms)
     * @param float $multiplier Pengali untuk backoff (Default: 2.0)
     */
    public function __construct(
        public readonly string $policy = 'NO_RETRY',
        public readonly int $maxAttempts = 3,
        public readonly int $baseDelayMilliseconds = 1000,
        public readonly int $maxDelayMilliseconds = 30000,
        public readonly float $multiplier = 2.0
    ) {}
}
