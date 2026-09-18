<?php

namespace App\Services\Reliability\Pipeline;

use App\Services\Reliability\Classification\FailureClassificationResult;
use App\Services\Reliability\RetryDecisionResult;
use App\Services\Reliability\Recovery\ResumeResult;

class RecoveryExecutionSummary
{
    /** @var FailureClassificationResult|null Hasil dari tahap klasifikasi kegagalan */
    public ?FailureClassificationResult $classificationResult = null;

    /** @var RetryDecisionResult|null Hasil dari tahap engine retry */
    public ?RetryDecisionResult $retryResult = null;

    /** @var ResumeResult|null Hasil dari tahap perumusan resume/checkpoint */
    public ?ResumeResult $resumeResult = null;

    /** @var string Keputusan final pipeline (Lihat RecoveryDecision) */
    public string $finalDecision = 'ABORT';
}
