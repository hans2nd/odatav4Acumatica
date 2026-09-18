<?php

namespace App\Services\Telemetry\Models;

class MetricsContext
{
    /**
     * @param array<string, mixed> $environmentTags Tag lingkungan global (Misal ['env' => 'production'])
     */
    public function __construct(
        public readonly array $environmentTags = []
    ) {}
}
