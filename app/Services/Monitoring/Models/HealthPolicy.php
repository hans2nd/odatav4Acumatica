<?php

namespace App\Services\Monitoring\Models;

class HealthPolicy
{
    /**
     * @param int $maxRepeatedFailures Batas jumlah kegagalan berturut-turut sebelum dianggap CRITICAL
     * @param int $stalledThresholdSeconds Batas waktu (detik) eksekusi berjalan tanpa selesai sebelum dianggap WARNING/STALLED
     */
    public function __construct(
        public readonly int $maxRepeatedFailures = 3,
        public readonly int $stalledThresholdSeconds = 3600
    ) {}
}
