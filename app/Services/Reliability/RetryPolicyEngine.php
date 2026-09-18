<?php

namespace App\Services\Reliability;

use App\Services\Reliability\Contracts\RetryPolicyEngineInterface;
use App\Services\Reliability\Contracts\RetryEligibilityEvaluatorInterface;
use App\Services\Reliability\Policies\RetryPolicyRegistry;

class RetryPolicyEngine implements RetryPolicyEngineInterface
{
    private RetryStatistics $statistics;

    public function __construct(
        private readonly RetryEligibilityEvaluatorInterface $evaluator,
        private readonly RetryPolicyRegistry $policyRegistry
    ) {
        $this->statistics = new RetryStatistics();
    }

    public function evaluate(RetryRequest $request, RetryContext $context): RetryDecisionResult
    {
        $startTime = microtime(true);
        $this->statistics->totalEvaluations++;

        $isEligible = $this->evaluator->isEligible($request->exception, $request->currentAttempt, $context);

        if (!$isEligible) {
            $this->statistics->totalRejected++;
            $this->statistics->durationSeconds += round(microtime(true) - $startTime, 4);
            
            return new RetryDecisionResult(
                false,
                0,
                $this->evaluator->getRejectionReason()
            );
        }

        $this->statistics->totalEligible++;

        // Dapatkan Strategi (Exponential, Jitter, dll)
        $policy = $this->policyRegistry->get($context->policy);
        
        // Kalkulasi delay
        $delayMs = $policy->calculateDelay($request->currentAttempt, $context);

        $this->statistics->durationSeconds += round(microtime(true) - $startTime, 4);

        return new RetryDecisionResult(
            true,
            $delayMs,
            "Kelayakan terpenuhi. Menggunakan kebijakan {$context->policy}."
        );
    }
}
