<?php

namespace App\Services\Telemetry\Models;

class MetricSeries
{
    /**
     * @param MetricDefinition $definition Aturan metrik
     * @param array $aggregatedValues Hasil kalkulasi agregasi (Misal: ['sum' => 10.5, 'avg' => 2.1, 'count' => 5])
     * @param float $timestamp Waktu hasil series ini dibungkus
     */
    public function __construct(
        public readonly MetricDefinition $definition,
        public readonly array $aggregatedValues,
        public readonly float $timestamp
    ) {}
}
