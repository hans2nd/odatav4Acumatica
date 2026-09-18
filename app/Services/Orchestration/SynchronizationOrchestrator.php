<?php

namespace App\Services\Orchestration;

use App\Services\Orchestration\Contracts\SynchronizationOrchestratorInterface;
use App\Services\Orchestration\Contracts\SynchronizationLifecycleEventsInterface;
use App\Services\Orchestration\Enums\SynchronizationStatus;
use App\Services\Orchestration\Exceptions\SynchronizationOrchestratorException;
use Throwable;

class SynchronizationOrchestrator implements SynchronizationOrchestratorInterface
{
    /** @var array<SynchronizationLifecycleEventsInterface> */
    private array $lifecycles = [];

    public function __construct(
        private readonly SynchronizationStageRegistry $registry
    ) {}

    public function addLifecycle(SynchronizationLifecycleEventsInterface $lifecycle): void
    {
        $this->lifecycles[] = $lifecycle;
    }

    public function execute(SynchronizationExecutionRequest $request): SynchronizationExecutionResult
    {
        $startTime = microtime(true);
        $context = new SynchronizationExecutionContext($request);

        $this->triggerLifecycles('beforeSynchronization', $context);

        try {
            $stages = $this->registry->getStages();
            $pipeline = $this->createPipeline($stages);

            // Eksekusi Pipa Orkestrasi End-to-End
            $finalContext = $pipeline($context);

            $finalContext->metrics->executionDurationSeconds = round(microtime(true) - $startTime, 4);
            $finalContext->metrics->memoryUsageMb = round(memory_get_usage(true) / 1024 / 1024, 2);

            $result = new SynchronizationExecutionResult(
                SynchronizationStatus::COMPLETED,
                $finalContext->summary,
                $finalContext->metrics
            );

            $this->triggerLifecycles('afterSynchronization', $result, $context);
            $this->triggerLifecycles('onSynchronizationCompleted', $result);

            return $result;
        } catch (Throwable $e) {
            $context->metrics->executionDurationSeconds = round(microtime(true) - $startTime, 4);
            $context->summary->errors[] = $e->getMessage();
            
            $result = new SynchronizationExecutionResult(
                SynchronizationStatus::FAILED,
                $context->summary,
                $context->metrics,
                $e
            );

            $this->triggerLifecycles('afterSynchronization', $result, $context);

            throw new SynchronizationOrchestratorException("Fatal Error pada Orchestrator: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    private function createPipeline(array $stages): \Closure
    {
        $pipeline = function(SynchronizationExecutionContext $context) {
            return $context;
        };

        foreach (array_reverse($stages) as $stage) {
            $pipeline = function(SynchronizationExecutionContext $context) use ($stage, $pipeline) {
                $stageName = get_class($stage);
                $this->triggerLifecycles('beforeStage', $stageName, $context);
                
                try {
                    $resultContext = $stage->process($context, $pipeline);
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
