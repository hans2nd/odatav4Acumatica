<?php

namespace App\Services\Scheduling;

use App\Services\Scheduling\Contracts\SchedulerEngineInterface;
use App\Services\Scheduling\Contracts\SchedulerLifecycleEventsInterface;
use App\Services\Scheduling\Exceptions\SchedulerException;
use App\Services\Scheduling\Enums\TriggerSource;
use App\Services\Scheduling\Policies\ManualTriggerPolicy;
use App\Services\Orchestration\Contracts\SynchronizationOrchestratorInterface;
use App\Services\Orchestration\SynchronizationExecutionRequest;
use Throwable;

class SchedulerEngine implements SchedulerEngineInterface
{
    /** @var array<SchedulerLifecycleEventsInterface> */
    private array $lifecycles = [];
    private SchedulerMetrics $metrics;

    public function __construct(
        private readonly ScheduleRegistry $registry,
        private readonly SynchronizationOrchestratorInterface $orchestrator
    ) {
        $this->metrics = new SchedulerMetrics();
    }

    public function addLifecycle(SchedulerLifecycleEventsInterface $lifecycle): void
    {
        $this->lifecycles[] = $lifecycle;
    }

    public function schedule(ScheduleRequest $request, ScheduleContext $context): ScheduleResult
    {
        $startTime = microtime(true);
        $summary = new ScheduleExecutionSummary();

        $this->triggerLifecycles('beforeSchedule', $request);

        try {
            // Cegah duplikasi jika sedang berjalan
            if ($context->isRunning) {
                $summary->reason = "SKIPPED: Job {$request->jobId} is currently running.";
                $this->metrics->skippedJobs++;
                $this->metrics->executionDurationSeconds = round(microtime(true) - $startTime, 4);
                
                $result = new ScheduleResult(false, 'SKIPPED', $summary, $this->metrics);
                $this->triggerLifecycles('onExecutionSkipped', $result);
                return $result;
            }

            // Jika dipantik manual, langsung lolos evaluasi
            if ($request->triggerSource === TriggerSource::MANUAL) {
                $summary->reason = "TRIGGERED: Manual Execution.";
                return $this->executeOrchestrator($request, $summary, $startTime);
            }

            $definition = $this->registry->getDefinition($request->jobId);

            if (!$definition) {
                $summary->reason = "FAILED: Tidak ada definisi jadwal untuk Job {$request->jobId}.";
                $this->metrics->failedScheduling++;
                $this->metrics->executionDurationSeconds = round(microtime(true) - $startTime, 4);
                
                return new ScheduleResult(false, 'FAILED', $summary, $this->metrics);
            }

            if (!$definition->isActive) {
                $summary->reason = "SKIPPED: Definisi jadwal berstatus Nonaktif (Inactive).";
                $this->metrics->skippedJobs++;
                $this->metrics->executionDurationSeconds = round(microtime(true) - $startTime, 4);
                
                $result = new ScheduleResult(false, 'SKIPPED', $summary, $this->metrics);
                $this->triggerLifecycles('onExecutionSkipped', $result);
                return $result;
            }

            $isEligible = false;
            foreach ($this->registry->getPolicies() as $policy) {
                if ($policy->supports($definition->scheduleType)) {
                    $isEligible = $policy->isEligible($definition, $context);
                    break;
                }
            }

            if ($isEligible) {
                $summary->reason = "TRIGGERED: Memenuhi kelayakan jadwal {$definition->scheduleType}.";
                return $this->executeOrchestrator($request, $summary, $startTime);
            }

            $summary->reason = "SKIPPED: Belum memenuhi waktu jadwal.";
            $this->metrics->skippedJobs++;
            $this->metrics->executionDurationSeconds = round(microtime(true) - $startTime, 4);
            
            $result = new ScheduleResult(false, 'SKIPPED', $summary, $this->metrics);
            $this->triggerLifecycles('onExecutionSkipped', $result);
            return $result;
            
        } catch (Throwable $e) {
            $this->metrics->failedScheduling++;
            $this->metrics->executionDurationSeconds = round(microtime(true) - $startTime, 4);
            
            throw new SchedulerException("Kegagalan pada saat evaluasi mesin penjadwal: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    private function executeOrchestrator(ScheduleRequest $request, ScheduleExecutionSummary $summary, float $startTime): ScheduleResult
    {
        $this->triggerLifecycles('beforeExecution', $request);

        $this->metrics->scheduledExecutions++;
        
        // Memantik Orchestrator
        $executionRequest = new SynchronizationExecutionRequest($request->jobId, $request->jobId);
        $orchestratorResult = $this->orchestrator->execute($executionRequest);

        $this->metrics->executedJobs++;
        $this->metrics->lastExecutionTimestamp = date('c');
        $this->metrics->executionDurationSeconds = round(microtime(true) - $startTime, 4);

        $result = new ScheduleResult(true, 'TRIGGERED', $summary, $this->metrics);
        
        $this->triggerLifecycles('afterExecution', $request);
        $this->triggerLifecycles('onExecutionCompleted', $result);
        
        return $result;
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
