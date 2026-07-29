<?php

namespace App\Services\Monitoring\Models;

class MonitoringContext
{
    /**
     * @param ExecutionHistory $history Bahan baku rekam jejak untuk dievaluasi
     * @param HealthPolicy $policy Konfigurasi parameter batas keparahan untuk evaluasi aturan
     */
    public function __construct(
        public readonly ExecutionHistory $history,
        public readonly HealthPolicy $policy
    ) {}
}
