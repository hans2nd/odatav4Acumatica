<?php

namespace App\Services\Reliability\Classification;

use App\Services\Reliability\Contracts\FailureClassificationEngineInterface;
use App\Services\Reliability\Classification\Classifiers\FailureClassifierRegistry;
use App\Services\Reliability\Classification\Enums\FailureCategory;
use App\Services\Reliability\Classification\Enums\FailureSeverity;
use App\Services\Reliability\Classification\Enums\RecoveryHint;

class FailureClassificationEngine implements FailureClassificationEngineInterface
{
    private FailureStatistics $statistics;

    public function __construct(
        private readonly FailureClassifierRegistry $registry
    ) {
        $this->statistics = new FailureStatistics();
    }

    public function classify(FailureClassificationRequest $request, FailureContext $context): FailureClassificationResult
    {
        $startTime = microtime(true);
        $this->statistics->totalClassifications++;
        
        $exception = $request->exception;
        $classifiers = $this->registry->getClassifiers();

        // Mencari classifier pertama yang bersedia menangani exception tersebut
        foreach ($classifiers as $classifier) {
            if ($classifier->supports($exception)) {
                $result = $classifier->classify($exception, $context);
                
                $this->statistics->totalRecognized++;
                $this->statistics->durationSeconds += round(microtime(true) - $startTime, 4);
                
                return $result;
            }
        }

        $this->statistics->totalUnknown++;
        $this->statistics->durationSeconds += round(microtime(true) - $startTime, 4);

        // Fallback jika tidak ada satupun yang mengenali
        $diagnostic = new FailureDiagnostic(
            $exception->getMessage(),
            get_class($exception),
            $exception->getCode(),
            $exception->getFile(),
            $exception->getLine()
        );

        return new FailureClassificationResult(
            FailureCategory::UNKNOWN,
            FailureSeverity::CRITICAL,
            RecoveryHint::MANUAL_INTERVENTION,
            $diagnostic
        );
    }
}
