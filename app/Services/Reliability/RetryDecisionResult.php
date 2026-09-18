<?php

namespace App\Services\Reliability;

class RetryDecisionResult
{
    /**
     * @param bool $shouldRetry Keputusan boleh diulang atau tidak
     * @param int $delayMilliseconds Durasi jeda sebelum mencoba lagi
     * @param string $reason Alasan dari keputusan (misal: "Max attempt reached" atau "Transient error")
     */
    public function __construct(
        public readonly bool $shouldRetry,
        public readonly int $delayMilliseconds,
        public readonly string $reason
    ) {}
}
