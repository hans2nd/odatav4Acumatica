<?php

namespace App\Services\Observability;

use App\Services\Observability\Contracts\ObservabilityEngineInterface;
use App\Services\Observability\Contracts\ObservabilityLifecycleEventsInterface;
use App\Services\Observability\Exceptions\ObservabilityException;
use App\Services\Observability\Models\CorrelationId;
use App\Services\Observability\Models\ExecutionLog;
use App\Services\Observability\Models\ExecutionEvent;
use App\Services\Observability\Models\ExecutionTrace;
use App\Services\Observability\Models\ExecutionSpan;
use App\Services\Observability\Models\ObservabilityContext;
use App\Services\Observability\Models\ObservabilityRecord;
use App\Services\Observability\Models\ObservabilityResult;
use Illuminate\Support\Str;
use Throwable;

class ObservabilityEngine implements ObservabilityEngineInterface
{
    /** @var array<ObservabilityLifecycleEventsInterface> */
    private array $lifecycles = [];
    
    /** @var array<string, ExecutionTrace> Peta in-memory Trace per CorrelationId */
    private array $activeTraces = [];

    /** @var array<string, ObservabilityContext> Peta konteks */
    private array $activeContexts = [];

    private ObservabilityStatistics $statistics;

    public function __construct(
        private readonly ObservabilityRegistry $registry
    ) {
        $this->statistics = new ObservabilityStatistics();
    }

    public function addLifecycle(ObservabilityLifecycleEventsInterface $lifecycle): void
    {
        $this->lifecycles[] = $lifecycle;
    }

    public function startTrace(string $jobId, ?ObservabilityContext $context = null): CorrelationId
    {
        $context = $context ?? new ObservabilityContext();
        $this->triggerLifecycles('beforeObservation', $context);

        $uuid = (string) Str::uuid();
        $correlationId = new CorrelationId($uuid, $jobId);
        
        $trace = new ExecutionTrace($correlationId, microtime(true));
        
        $this->activeTraces[$uuid] = $trace;
        $this->activeContexts[$uuid] = $context;
        
        $this->triggerLifecycles('beforeRecording', $correlationId, $trace);

        return $correlationId;
    }

    public function startSpan(CorrelationId $correlationId, string $spanName): void
    {
        $trace = $this->getTraceOrFail($correlationId);
        
        // Buat span baru jika belum ada
        if (!isset($trace->spans[$spanName])) {
            $span = new ExecutionSpan($spanName, microtime(true));
            $trace->addSpan($span);
        }
    }

    public function endSpan(CorrelationId $correlationId, string $spanName, string $status = 'COMPLETED'): void
    {
        $trace = $this->getTraceOrFail($correlationId);
        
        if (isset($trace->spans[$spanName])) {
            $trace->spans[$spanName]->end($status);
            $this->statistics->totalSpans++;
        }
    }

    public function recordLog(CorrelationId $correlationId, string $spanName, ExecutionLog $log): void
    {
        $trace = $this->getTraceOrFail($correlationId);
        
        if (isset($trace->spans[$spanName])) {
            $trace->spans[$spanName]->addLog($log);
            $this->statistics->totalLogs++;
        }
    }

    public function recordEvent(CorrelationId $correlationId, string $spanName, ExecutionEvent $event): void
    {
        $trace = $this->getTraceOrFail($correlationId);
        
        if (isset($trace->spans[$spanName])) {
            $trace->spans[$spanName]->addEvent($event);
            $this->statistics->totalEvents++;
        }
    }

    public function endTrace(CorrelationId $correlationId): ObservabilityResult
    {
        $uuid = $correlationId->id;
        $trace = $this->getTraceOrFail($correlationId);
        $context = $this->activeContexts[$uuid];

        $trace->end();
        $this->statistics->totalTraces++;

        $record = new ObservabilityRecord($trace, $context);
        
        $providers = $this->registry->getProviders();
        $publishedCount = 0;

        foreach ($providers as $provider) {
            try {
                $provider->publish($record);
                $publishedCount++;
            } catch (Throwable $e) {
                $this->statistics->failedPublish++;
                $this->triggerLifecycles('onObservationFailure', $e);
            }
        }

        $result = new ObservabilityResult($publishedCount > 0, $publishedCount);

        // Hapus dari memori setelah di-publish
        unset($this->activeTraces[$uuid]);
        unset($this->activeContexts[$uuid]);

        $this->triggerLifecycles('afterRecording', $correlationId, $result);
        $this->triggerLifecycles('onObservationCompleted', $result);
        $this->triggerLifecycles('afterObservation', $context);

        return $result;
    }

    private function getTraceOrFail(CorrelationId $correlationId): ExecutionTrace
    {
        $uuid = $correlationId->id;
        if (!isset($this->activeTraces[$uuid])) {
            throw new ObservabilityException("Trace tidak ditemukan untuk Correlation ID: {$uuid}");
        }
        return $this->activeTraces[$uuid];
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
