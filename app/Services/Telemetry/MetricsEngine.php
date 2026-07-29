<?php

namespace App\Services\Telemetry;

use App\Services\Telemetry\Contracts\MetricsEngineInterface;
use App\Services\Telemetry\Contracts\MetricsLifecycleEventsInterface;
use App\Services\Telemetry\Contracts\MetricAggregatorInterface;
use App\Services\Telemetry\Exceptions\MetricsException;
use App\Services\Telemetry\Models\MetricsRequest;
use App\Services\Telemetry\Models\MetricsContext;
use App\Services\Telemetry\Models\MetricsResult;
use App\Services\Telemetry\Models\MetricsStatistics;
use App\Services\Telemetry\Models\TelemetryBatch;
use Throwable;

class MetricsEngine implements MetricsEngineInterface
{
    /** @var array<MetricsLifecycleEventsInterface> */
    private array $lifecycles = [];

    public function __construct(
        private readonly MetricRegistry $registry,
        private readonly MetricAggregatorInterface $aggregator
    ) {}

    public function addLifecycle(MetricsLifecycleEventsInterface $lifecycle): void
    {
        $this->lifecycles[] = $lifecycle;
    }

    public function collect(MetricsRequest $request, MetricsContext $context): MetricsResult
    {
        $startTime = microtime(true);
        $statistics = new MetricsStatistics();
        $statistics->totalRawSamplesProcessed = count($request->rawSamples);

        $this->triggerLifecycles('beforeMetricCollection', $request, $context);
        $this->triggerLifecycles('beforeAggregation', $request);

        try {
            // Kelompokkan sampel berdasarkan Nama Metrik (Bisa dikembangkan lebih lanjut berdasarkan dimensi)
            $groupedSamples = [];
            foreach ($request->rawSamples as $sample) {
                $metricName = $sample->definition->name;
                
                // Pastikan metrik tersebut sudah diregistrasi (diakui)
                if ($this->registry->getDefinition($metricName)) {
                    $groupedSamples[$metricName][] = $sample;
                }
            }

            $seriesList = [];
            foreach ($groupedSamples as $metricName => $samples) {
                $definition = $this->registry->getDefinition($metricName);
                if ($definition) {
                    $series = $this->aggregator->aggregate($definition, $samples);
                    $seriesList[] = $series;
                }
            }

            $statistics->totalSeriesGenerated = count($seriesList);
            $this->triggerLifecycles('afterAggregation', $seriesList);

            // Paketkan ke dalam TelemetryBatch
            $batch = new TelemetryBatch($seriesList, $request->rawEvents);

            // Ekspor ke semua Exporters
            $exporters = $this->registry->getExporters();
            foreach ($exporters as $exporter) {
                // Biarkan Exporter mengurus pengirimannya, tangkap error bila gagal di Exporter agar tidak crash
                try {
                    $exporter->export($batch);
                } catch (Throwable $e) {
                    // Log error exporter internal
                    $this->triggerLifecycles('onMetricFailure', $e);
                }
            }

            $statistics->aggregationDurationSeconds = round(microtime(true) - $startTime, 4);
            $result = new MetricsResult(true, count($seriesList), $statistics);

            $this->triggerLifecycles('onMetricCompleted', $result);
            $this->triggerLifecycles('afterMetricCollection', $result);

            return $result;
            
        } catch (Throwable $e) {
            $this->triggerLifecycles('onMetricFailure', $e);
            throw new MetricsException("Kegagalan komputasi agregat metrik: " . $e->getMessage(), $e->getCode(), $e);
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
