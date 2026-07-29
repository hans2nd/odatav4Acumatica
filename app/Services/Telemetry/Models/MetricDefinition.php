<?php

namespace App\Services\Telemetry\Models;

class MetricDefinition
{
    /**
     * @param string $name Nama Metrik (Misal: 'execution_duration_seconds')
     * @param string $type Jenis metrik (Misal: 'counter', 'histogram', 'gauge')
     * @param string $description Penjelasan singkat fungsi metrik
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $description = ''
    ) {}
}
