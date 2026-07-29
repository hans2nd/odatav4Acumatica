<?php

namespace App\Services\Reliability\Classification;

class FailureClassificationResult
{
    /**
     * @param string $category Kategori kegagalan (Lihat FailureCategory)
     * @param string $severity Tingkat keparahan (Lihat FailureSeverity)
     * @param string $recoveryHint Saran pemulihan (Lihat RecoveryHint)
     * @param FailureDiagnostic $diagnostic Detail metadata diagnosis
     */
    public function __construct(
        public readonly string $category,
        public readonly string $severity,
        public readonly string $recoveryHint,
        public readonly FailureDiagnostic $diagnostic
    ) {}
}
