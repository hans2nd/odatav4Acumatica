<?php

namespace App\Services\Alerting\Models;

use App\Services\Monitoring\Models\MonitoringResult;
use App\Services\Telemetry\Models\MetricsResult;

class AlertContext
{
    /**
     * @param MonitoringResult $monitoring Hasil pemantauan dari Fase 7.4
     * @param MetricsResult $metrics Hasil metrik dari Fase 7.5
     * @param AlertPolicy $policy Kebijakan batas alarm (seperti memory threshold)
     */
    public function __construct(
        public readonly MonitoringResult $monitoring,
        public readonly MetricsResult $metrics,
        public readonly AlertPolicy $policy
    ) {}
}
