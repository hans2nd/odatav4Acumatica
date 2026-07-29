<?php

namespace App\Services\Alerting;

use App\Services\Alerting\Contracts\AlertingEngineInterface;
use App\Services\Alerting\Contracts\AlertingLifecycleEventsInterface;
use App\Services\Alerting\Contracts\AlertDispatcherInterface;
use App\Services\Alerting\Exceptions\AlertingException;
use App\Services\Alerting\Models\AlertRequest;
use App\Services\Alerting\Models\AlertContext;
use App\Services\Alerting\Models\AlertResult;
use App\Services\Alerting\Models\AlertStatistics;
use App\Services\Alerting\Models\NotificationMessage;
use Throwable;

class AlertingEngine implements AlertingEngineInterface
{
    /** @var array<AlertingLifecycleEventsInterface> */
    private array $lifecycles = [];

    public function __construct(
        private readonly AlertRegistry $registry,
        private readonly AlertDispatcherInterface $dispatcher
    ) {}

    public function addLifecycle(AlertingLifecycleEventsInterface $lifecycle): void
    {
        $this->lifecycles[] = $lifecycle;
    }

    public function evaluate(AlertRequest $request, AlertContext $context): AlertResult
    {
        $startTime = microtime(true);
        $statistics = new AlertStatistics();
        $allNotificationResults = [];

        $this->triggerLifecycles('beforeAlertEvaluation', $request, $context);

        try {
            $rules = $this->registry->getRules();

            foreach ($rules as $rule) {
                $statistics->rulesEvaluated++;

                $definition = $rule->evaluate($context);

                // Jika terdeteksi FIRING, definisi dikembalikan
                if ($definition !== null) {
                    $statistics->alertsFired++;
                    
                    // Bangun struktur pesan berdasarkan definisi alarm
                    $message = new NotificationMessage(
                        title: $definition->title,
                        body: "Terdeteksi anomali untuk Job ID: {$request->jobId}",
                        severity: $definition->severity,
                        metadata: [
                            'job_id' => $request->jobId,
                            'alert_id' => $definition->id
                        ]
                    );

                    $this->triggerLifecycles('beforeNotification', $request);

                    // Panggil Dispatcher untuk merutekan ke Provider
                    $dispatchResults = $this->dispatcher->dispatch($definition, $message);
                    
                    // Kompilasi hasil pengiriman
                    foreach ($dispatchResults as $dr) {
                        $allNotificationResults[] = $dr;
                        if ($dr->success) {
                            $statistics->notificationsSent++;
                        } else {
                            $statistics->notificationsFailed++;
                        }
                    }

                    $this->triggerLifecycles('afterNotification', $dispatchResults);
                }
            }

            $statistics->evaluationDurationSeconds = round(microtime(true) - $startTime, 4);
            $result = new AlertResult(true, $allNotificationResults, $statistics);

            $this->triggerLifecycles('afterAlertEvaluation', $result);
            $this->triggerLifecycles('onAlertCompleted', $result);

            return $result;

        } catch (Throwable $e) {
            $this->triggerLifecycles('onAlertFailure', $e);
            throw new AlertingException("Gagal melakukan evaluasi peringatan: " . $e->getMessage(), $e->getCode(), $e);
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
