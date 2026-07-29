<?php

namespace App\Services\Telemetry;

use App\Services\Telemetry\Contracts\TelemetryExporterInterface;
use App\Services\Telemetry\Models\MetricDefinition;

class MetricRegistry
{
    /** @var array<string, MetricDefinition> */
    private array $definitions = [];

    /** @var array<TelemetryExporterInterface> */
    private array $exporters = [];

    public function registerDefinition(MetricDefinition $definition): void
    {
        $this->definitions[$definition->name] = $definition;
    }

    public function getDefinition(string $name): ?MetricDefinition
    {
        return $this->definitions[$name] ?? null;
    }

    public function registerExporter(TelemetryExporterInterface $exporter): void
    {
        $this->exporters[] = $exporter;
    }

    /**
     * @return array<TelemetryExporterInterface>
     */
    public function getExporters(): array
    {
        return $this->exporters;
    }
}
