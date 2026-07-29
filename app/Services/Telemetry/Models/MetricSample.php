<?php

namespace App\Services\Telemetry\Models;

class MetricSample
{
    /**
     * @param MetricDefinition $definition Aturan definisi pembentuk metrik
     * @param float $value Nilai pengukuran mentah tunggal
     * @param float $timestamp Waktu pengukuran diambil
     * @param array<MetricDimension> $dimensions Array dimensi pemilahan
     * @param array<MetricLabel> $labels Array tag atau label
     */
    public function __construct(
        public readonly MetricDefinition $definition,
        public readonly float $value,
        public readonly float $timestamp,
        public readonly array $dimensions = [],
        public readonly array $labels = []
    ) {}
}
