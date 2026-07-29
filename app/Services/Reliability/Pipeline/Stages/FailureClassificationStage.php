<?php

namespace App\Services\Reliability\Pipeline\Stages;

use App\Services\Reliability\Contracts\ReliabilityStageInterface;
use App\Services\Reliability\Pipeline\ReliabilityPipelineExecutionContext;
use App\Services\Reliability\Contracts\FailureClassificationEngineInterface;
use App\Services\Reliability\Classification\FailureClassificationRequest;
use App\Services\Reliability\Classification\FailureContext;
use App\Services\Reliability\Pipeline\Enums\RecoveryDecision;
use Closure;

class FailureClassificationStage implements ReliabilityStageInterface
{
    public function __construct(
        private readonly FailureClassificationEngineInterface $engine
    ) {}

    public function handle(ReliabilityPipelineExecutionContext $context, Closure $next): ReliabilityPipelineExecutionContext
    {
        $request = new FailureClassificationRequest($context->originalContext->exception);
        
        // Gunakan konteks kustom jika ada, atau default
        $failureContext = $context->originalContext->failureContext ?? new FailureContext();
        
        $result = $this->engine->classify($request, $failureContext);
        
        $context->summary->classificationResult = $result;
        $context->summary->finalDecision = $result->recoveryHint;
        
        $context->metrics->failureCount++;
        
        if ($result->recoveryHint === RecoveryDecision::ABORT) {
            $context->metrics->abortedCount++;
        } elseif ($result->recoveryHint === RecoveryDecision::SKIP) {
            $context->metrics->skippedCount++;
        } elseif ($result->recoveryHint === RecoveryDecision::ESCALATE) {
            $context->metrics->escalatedCount++;
        }

        return $next($context);
    }
}
