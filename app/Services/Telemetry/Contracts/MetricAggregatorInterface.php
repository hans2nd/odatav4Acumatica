<?php

namespace App\Services\Telemetry\Contracts;

use App\Services\Telemetry\Models\MetricSample;
use App\Services\Telemetry\Models\MetricSeries;
use App\Services\Telemetry\Models\MetricDefinition;

interface MetricAggregatorInterface
{
    /**
     * Mengkalkulasi nilai gabungan dari kumpulan sampel dan mengembalikannya dalam bentuk Series.
     * 
     * @param MetricDefinition $definition
     * @param array<MetricSample> $samples
     * @return MetricSeries
     */
    public function aggregate(MetricDefinition $definition, array $samples): MetricSeries;
}
