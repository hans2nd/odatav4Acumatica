<?php

namespace App\Services\Monitoring\Models;

class ExecutionHealth
{
    /**
     * @param string $ruleName Nama aturan pemantauan yang menghasilkan status ini
     * @param string $status Status hasil evaluasi (Lihat HealthStatus)
     * @param string $reason Penjelasan singkat hasil evaluasi
     */
    public function __construct(
        public readonly string $ruleName,
        public readonly string $status,
        public readonly string $reason
    ) {}
}
