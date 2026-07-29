<?php

namespace App\Services\Alerting\Contracts;

use App\Services\Alerting\Models\AlertContext;
use App\Services\Alerting\Models\AlertDefinition;

interface AlertRuleInterface
{
    /**
     * Mengevaluasi konteks (monitoring/metrik) dan mengembalikan definisi alarm (Jika berstatus FIRING).
     * Jika aman (RESOLVED), maka kembalikan null.
     */
    public function evaluate(AlertContext $context): ?AlertDefinition;
}
