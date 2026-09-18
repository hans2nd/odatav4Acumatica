<?php

namespace App\Services\Monitoring\Models;

class ExecutionHistory
{
    /**
     * @param array $recentStatuses Daftar status eksekusi terbaru. Berurutan dari yang terbaru (indeks 0).
     * @param bool $isCurrentlyRunning Apakah sinkronisasi ini terdeteksi masih berjalan (belum ada status selesai).
     * @param float|null $currentRunningDurationSeconds Berapa lama sinkronisasi saat ini berjalan (jika isCurrentlyRunning = true).
     */
    public function __construct(
        public readonly array $recentStatuses = [],
        public readonly bool $isCurrentlyRunning = false,
        public readonly ?float $currentRunningDurationSeconds = null
    ) {}
}
