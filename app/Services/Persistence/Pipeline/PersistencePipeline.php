<?php

namespace App\Services\Persistence\Pipeline;

use App\Services\Persistence\Contracts\PersistencePipelineInterface;
use App\Services\Persistence\Contracts\PersistenceLifecycleEventsInterface;
use App\Services\Persistence\Pipeline\PersistencePipelineContext;
use App\Services\Persistence\Pipeline\PersistencePipelineResult;
use App\Services\Persistence\Pipeline\PersistencePipelineExecutionContext;
use App\Services\Persistence\Exceptions\PersistencePipelineException;
use Throwable;

class PersistencePipeline implements PersistencePipelineInterface
{
    /** @var array<PersistenceLifecycleEventsInterface> */
    private array $lifecycles = [];

    public function __construct(
        private readonly PersistenceStageRegistry $registry
    ) {}

    public function addLifecycle(PersistenceLifecycleEventsInterface $lifecycle): void
    {
        $this->lifecycles[] = $lifecycle;
    }

    public function process(PersistencePipelineContext $context): PersistencePipelineResult
    {
        $startTime = microtime(true);
        $execContext = new PersistencePipelineExecutionContext($context);

        $this->triggerLifecycles('beforePersistence', $context);

        try {
            // Bangun alur middleware / pipeline dari deretan Stage di dalam Registry
            $stages = $this->registry->getStages();
            $pipeline = $this->createPipeline($stages, $execContext);

            // Eksekusi keseluruhan pipeline
            $finalContext = $pipeline($execContext);

            // Pengukuran memory (Hanya aproksimasi)
            $finalContext->metrics->memoryUsageMb = round(memory_get_usage(true) / 1024 / 1024, 2);
            $finalContext->metrics->durationSeconds = round(microtime(true) - $startTime, 4);

            $result = new PersistencePipelineResult(true, $finalContext->metrics);
            
            $this->triggerLifecycles('afterPersistence', $result, $context);
            
            return $result;
        } catch (Throwable $e) {
            $execContext->metrics->durationSeconds = round(microtime(true) - $startTime, 4);
            $execContext->metrics->totalFailed += count($context->upsertRecords) + count($context->deleteRecords);
            
            $result = new PersistencePipelineResult(false, $execContext->metrics, $e);
            $this->triggerLifecycles('afterPersistence', $result, $context);

            throw new PersistencePipelineException("Kegagalan pada Persistence Pipeline: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    private function createPipeline(array $stages, PersistencePipelineExecutionContext $initialContext): \Closure
    {
        $pipeline = function(PersistencePipelineExecutionContext $context) {
            return $context;
        };

        // Balik urutan karena pembungkusan middleware bekerja dari dalam ke luar
        foreach (array_reverse($stages) as $stage) {
            $pipeline = function(PersistencePipelineExecutionContext $context) use ($stage, $pipeline) {
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
