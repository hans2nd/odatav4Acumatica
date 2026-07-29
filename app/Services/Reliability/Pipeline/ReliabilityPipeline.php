<?php

namespace App\Services\Reliability\Pipeline;

use App\Services\Reliability\Contracts\ReliabilityPipelineInterface;
use App\Services\Reliability\Contracts\ReliabilityLifecycleEventsInterface;
use App\Services\Reliability\Pipeline\ReliabilityPipelineContext;
use App\Services\Reliability\Pipeline\ReliabilityPipelineResult;
use App\Services\Reliability\Pipeline\ReliabilityPipelineExecutionContext;
use App\Services\Reliability\Exceptions\ReliabilityPipelineException;
use App\Services\Reliability\Pipeline\Enums\RecoveryDecision;
use Throwable;

class ReliabilityPipeline implements ReliabilityPipelineInterface
{
    /** @var array<ReliabilityLifecycleEventsInterface> */
    private array $lifecycles = [];

    public function __construct(
        private readonly ReliabilityStageRegistry $registry
    ) {}

    public function addLifecycle(ReliabilityLifecycleEventsInterface $lifecycle): void
    {
        $this->lifecycles[] = $lifecycle;
    }

    public function process(ReliabilityPipelineContext $context): ReliabilityPipelineResult
    {
        $startTime = microtime(true);
        $execContext = new ReliabilityPipelineExecutionContext($context);

        $this->triggerLifecycles('beforeRecovery', $context);

        try {
            $stages = $this->registry->getStages();
            $pipeline = $this->createPipeline($stages, $execContext);

            // Eksekusi saluran (pipeline)
            $finalContext = $pipeline($execContext);

            // Jika hasil akhir menjurus pada upaya memulihkan yang berhasil dikalkulasi
            $successDecisions = [RecoveryDecision::RETRY, RecoveryDecision::RESUME, RecoveryDecision::SKIP];
            if (in_array($finalContext->summary->finalDecision, $successDecisions)) {
                $finalContext->metrics->successfulRecoveries++;
            }

            $finalContext->metrics->memoryUsageMb = round(memory_get_usage(true) / 1024 / 1024, 2);
            $finalContext->metrics->recoveryDurationSeconds = round(microtime(true) - $startTime, 4);

            $result = new ReliabilityPipelineResult(true, $finalContext->summary, $finalContext->metrics);
            
            $this->triggerLifecycles('afterRecovery', $result, $context);
            
            return $result;
        } catch (Throwable $e) {
            $execContext->metrics->recoveryDurationSeconds = round(microtime(true) - $startTime, 4);
            $execContext->summary->finalDecision = RecoveryDecision::ABORT;
            
            $result = new ReliabilityPipelineResult(false, $execContext->summary, $execContext->metrics, $e);
            $this->triggerLifecycles('afterRecovery', $result, $context);

            throw new ReliabilityPipelineException("Kegagalan pada saluran Reliability Pipeline: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    private function createPipeline(array $stages, ReliabilityPipelineExecutionContext $initialContext): \Closure
    {
        $pipeline = function(ReliabilityPipelineExecutionContext $context) {
            return $context;
        };

        // Balik urutan pembungkusan stage
        foreach (array_reverse($stages) as $stage) {
            $pipeline = function(ReliabilityPipelineExecutionContext $context) use ($stage, $pipeline) {
                $stageName = get_class($stage);
                $this->triggerLifecycles('beforeStage', $stageName, $context);
                
                try {
                    $resultContext = $stage->handle($context, $pipeline);
                    $this->triggerLifecycles('afterStage', $stageName, $resultContext);
                    return $resultContext;
                } catch (Throwable $e) {
                    $this->triggerLifecycles('onStageFailure', $stageName, $e, $context);
                    throw $e;
                }
            };
        }

        return $pipeline;
    }

    private function triggerLifecycles(string $method, ...$args): void
    {
        foreach ($this->lifecycles as $lifecycle) {
            if (method_exists($lifecycle, $method)) {
                $lifecycle->$method(...$args);
            }
        }
    }
}
