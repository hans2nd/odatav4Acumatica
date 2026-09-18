<?php

namespace App\Services\Alerting\Models;

class AlertResult
{
    /**
     * @param bool $success Apakah sistem berhasil mengevaluasi dan merutekan alarm (bukan berarti alarm meletus)
     * @param array<NotificationResult> $notificationResults Hasil pengiriman dari masing-masing penyedia (provider)
     * @param AlertStatistics $statistics Statistik kinerja mesin
     */
    public function __construct(
        public readonly bool $success,
        public readonly array $notificationResults,
        public readonly AlertStatistics $statistics
    ) {}
}
