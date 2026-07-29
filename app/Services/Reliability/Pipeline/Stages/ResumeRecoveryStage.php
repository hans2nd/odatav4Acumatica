<?php

namespace App\Services\Reliability\Pipeline\Stages;

use App\Services\Reliability\Contracts\ReliabilityStageInterface;
use App\Services\Reliability\Pipeline\ReliabilityPipelineExecutionContext;
use App\Services\Reliability\Contracts\ResumeEngineInterface;
use App\Services\Reliability\Recovery\ResumeRequest;
use App\Services\Reliability\Recovery\ResumeContext;
use App\Services\Reliability\Pipeline\Enums\RecoveryDecision;
use Closure;

class ResumeRecoveryStage implements ReliabilityStageInterface
{
    public function __construct(
        private readonly ResumeEngineInterface $engine
    ) {}

    public function handle(ReliabilityPipelineExecutionContext $context, Closure $next): ReliabilityPipelineExecutionContext
    {
        // Hanya jalan jika instruksi sebelumnya adalah RESUME, atau ABORT (namun sistem butuh menyimpan titik aman untuk restart nanti)
        $classificationResult = $context->summary->classificationResult;
        
        $shouldResume = $classificationResult && $classificationResult->recoveryHint === RecoveryDecision::RESUME;
        
        if ($shouldResume) {
            
            $request = new ResumeRequest($context->originalContext->jobId);
            $resumeContext = $context->originalContext->resumeContext ?? new ResumeContext();

            $result = $this->engine->resume($request, $resumeContext);

            $context->summary->resumeResult = $result;

            if ($result->isSuccess) {
                $context->summary->finalDecision = RecoveryDecision::RESUME;
                $context->metrics->resumeCount++;
            } else {
                $context->summary->finalDecision = RecoveryDecision::ABORT;
                $context->metrics->abortedCount++;
            }
        }

        return $next($context);
    }
}
