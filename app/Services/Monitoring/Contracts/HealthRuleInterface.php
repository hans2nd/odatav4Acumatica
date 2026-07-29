<?php

namespace App\Services\Monitoring\Contracts;

use App\Services\Monitoring\Models\ExecutionHistory;
use App\Services\Monitoring\Models\HealthPolicy;
use App\Services\Monitoring\Models\ExecutionHealth;

interface HealthRuleInterface
{
    /**
     * Mengevaluasi kondisi kesehatan berdasarkan jejak sejarah eksekusi dan batasan parameter
     */
    public function evaluate(ExecutionHistory $history, HealthPolicy $policy): ExecutionHealth;
}
