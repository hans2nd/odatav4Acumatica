<?php

namespace App\Services\Telemetry\Aggregators;

use App\Services\Telemetry\Contracts\MetricAggregatorInterface;
use App\Services\Telemetry\Models\MetricDefinition;
use App\Services\Telemetry\Models\MetricSample;
use App\Services\Telemetry\Models\MetricSeries;

class StandardAggregator implements MetricAggregatorInterface
{
    /**
     * Melakukan perhitungan dasar: count, sum, min, max, avg.
     */
    public function aggregate(MetricDefinition $definition, array $samples): MetricSeries
    {
        $count = count($samples);
        if ($count === 0) {
            return new MetricSeries($definition, ['count' => 0], microtime(true));
        }

        $sum = 0.0;
        $min = PHP_FLOAT_MAX;
        $max = PHP_FLOAT_MIN;

        foreach ($samples as $sample) {
            $val = $sample->value;
            $sum += $val;
            if ($val < $min) {
                $min = $val;
            }
            if ($val > $max) {
                $max = $val;
            }
        }

        $avg = $sum / $count;

        $aggregatedValues = [
            'count' => $count,
            'sum' => $sum,
            'min' => $min,
            'max' => $max,
            'avg' => $avg,
        ];

        return new MetricSeries($definition, $aggregatedValues, microtime(true));
    }
}
