<?php

namespace App\Services\Alerting\Models;

class AlertPolicy
{
    /**
     * @param int $suppressionWindowSeconds Jendela penahanan (detik) untuk menghindari alarm beruntun (spam)
     * @param float $memoryThresholdMb Batas maksimum memori dalam MB (Jika diperlukan)
     */
    public function __construct(
        public readonly int $suppressionWindowSeconds = 300,
        public readonly float $memoryThresholdMb = 512.0
    ) {}
}
