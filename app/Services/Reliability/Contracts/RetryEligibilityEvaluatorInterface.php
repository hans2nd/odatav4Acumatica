<?php

namespace App\Services\Reliability\Contracts;

use App\Services\Reliability\RetryContext;
use Throwable;

interface RetryEligibilityEvaluatorInterface
{
    /**
     * Memeriksa kelayakan apakah eksepsi dan status percobaan ini boleh diulang.
     * 
     * @param Throwable $exception Eksepsi penyebab kegagalan
     * @param int $currentAttempt Percobaan keberapa kegagalan ini terjadi
     * @param RetryContext $context Konteks batasan maksimal dsb
     * @return bool True jika boleh dicoba ulang
     */
    public function isEligible(Throwable $exception, int $currentAttempt, RetryContext $context): bool;

    /**
     * Memberikan alasan penolakan (jika isEligible == false)
     */
    public function getRejectionReason(): string;
}
