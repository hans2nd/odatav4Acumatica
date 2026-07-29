<?php

namespace App\Services\Monitoring;

use App\Services\Monitoring\Contracts\MonitoringEngineInterface;
use App\Services\Monitoring\Contracts\MonitoringLifecycleEventsInterface;
use App\Services\Monitoring\Exceptions\MonitoringException;
use App\Services\Monitoring\Enums\HealthStatus;
use App\Services\Monitoring\Models\MonitoringRequest;
use App\Services\Monitoring\Models\MonitoringContext;
use App\Services\Monitoring\Models\MonitoringResult;
use App\Services\Monitoring\Models\MonitoringStatistics;
use App\Services\Monitoring\Models\PipelineHealthSummary;
use App\Services\Monitoring\Models\ExecutionHealth;
use Throwable;

class MonitoringEngine implements MonitoringEngineInterface
{
    /** @var array<MonitoringLifecycleEventsInterface> */
    private array $lifecycles = [];

    public function __construct(
        private readonly MonitoringRegistry $registry
    ) {}

    public function addLifecycle(MonitoringLifecycleEventsInterface $lifecycle): void
    {
        $this->lifecycles[] = $lifecycle;
    }

    public function evaluate(MonitoringRequest $request, MonitoringContext $context): MonitoringResult
    {
        $startTime = microtime(true);
        $statistics = new MonitoringStatistics();

        $this->triggerLifecycles('beforeMonitoring', $request, $context);
        $this->triggerLifecycles('beforeEvaluation', $context);

        try {
            $rules = $this->registry->getRules();
            $healthResults = [];
            $worstSeverity = 0;
            $overallStatus = HealthStatus::HEALTHY;

            foreach ($rules as $rule) {
                $statistics->rulesEvaluated++;
                $health = $rule->evaluate($context->history, $context->policy);
                $healthResults[] = $health;

                $currentSeverity = HealthStatus::getSeverity($health->status);
                
                // Worst Wins Strategy
                if ($currentSeverity > $worstSeverity) {
                    $worstSeverity = $currentSeverity;
                    $overallStatus = $health->status;
                }
            }

            $summary = new PipelineHealthSummary($overallStatus, $healthResults);
            $statistics->evaluationDurationSeconds = round(microtime(true) - $startTime, 4);

            $result = new MonitoringResult($request->jobId, $summary, $statistics);

            $this->triggerLifecycles('afterEvaluation', $result);
            $this->triggerLifecycles('onMonitoringCompleted', $result);
            $this->triggerLifecycles('afterMonitoring', $result);

            return $result;
        } catch (Throwable $e) {
            $this->triggerLifecycles('onMonitoringFailure', $e);
            throw new MonitoringException("Gagal melakukan evaluasi pemantauan kesehatan: " . $e->getMessage(), $e->getCode(), $e);
        }
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
