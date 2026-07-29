<?php

namespace App\Services\Reliability\Pipeline\Stages;

use App\Services\Reliability\Contracts\ReliabilityStageInterface;
use App\Services\Reliability\Pipeline\ReliabilityPipelineExecutionContext;
use App\Services\Reliability\Contracts\RetryPolicyEngineInterface;
use App\Services\Reliability\RetryRequest;
use App\Services\Reliability\RetryContext;
use App\Services\Reliability\Pipeline\Enums\RecoveryDecision;
use Closure;

class RetryPolicyStage implements ReliabilityStageInterface
{
    public function __construct(
        private readonly RetryPolicyEngineInterface $engine
    ) {}

    public function handle(ReliabilityPipelineExecutionContext $context, Closure $next): ReliabilityPipelineExecutionContext
    {
        // Hanya jalan jika instruksi sebelumnya adalah RETRY
        if ($context->summary->classificationResult && $context->summary->classificationResult->recoveryHint === RecoveryDecision::RETRY) {
            
            $request = new RetryRequest($context->originalContext->exception, $context->originalContext->currentAttempt);
            $retryContext = $context->originalContext->retryContext ?? new RetryContext();

            $result = $this->engine->evaluate($request, $retryContext);

            $context->summary->retryResult = $result;

            if ($result->shouldRetry) {
                $context->summary->finalDecision = RecoveryDecision::RETRY;
                $context->metrics->retryCount++;
            } else {
                // Evaluator menolak retry (mungkin max attempt reached), maka jatuhkan putusan ABORT
                $context->summary->finalDecision = RecoveryDecision::ABORT;
                $context->metrics->abortedCount++;
            }
        }

        return $next($context);
    }
}
