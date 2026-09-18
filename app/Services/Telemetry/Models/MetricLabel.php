<?php

namespace App\Services\Telemetry\Models;

class MetricLabel
{
    public function __construct(
        public readonly string $key,
        public readonly string $value
    ) {}
}
