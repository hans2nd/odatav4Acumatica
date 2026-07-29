<?php

namespace App\Services\Reliability\Pipeline;

use Throwable;
use App\Services\Reliability\Classification\FailureContext;
use App\Services\Reliability\RetryContext;
use App\Services\Reliability\Recovery\ResumeContext;

class ReliabilityPipelineContext
{
    /**
     * @param Throwable $exception Eksepsi/Kesalahan yang terjadi pada sistem
     * @param string $jobId ID Sinkronisasi yang gagal
     * @param int $currentAttempt Keberapakali kegagalan ini terjadi
     * @param FailureContext|null $failureContext Konteks spesifik klasifikasi (optional)
     * @param RetryContext|null $retryContext Konteks spesifik retry (optional)
     * @param ResumeContext|null $resumeContext Konteks spesifik resume (optional)
     */
    public function __construct(
        public readonly Throwable $exception,
        public readonly string $jobId,
        public readonly int $currentAttempt = 1,
        public readonly ?FailureContext $failureContext = null,
        public readonly ?RetryContext $retryContext = null,
        public readonly ?ResumeContext $resumeContext = null
    ) {}
}
