<?php

namespace App\Services\Telemetry\Models;

class MetricDimension
{
    public function __construct(
        public readonly string $key,
        public readonly string $value
    ) {}
}
