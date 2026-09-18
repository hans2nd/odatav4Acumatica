<?php

namespace App\Services\Scheduling;

class ScheduleDefinition
{
    /**
     * @param string $jobId ID Sinkronisasi (Misal: 'sync-invoices')
     * @param string $scheduleType Tipe jadwal (Lihat ScheduleType)
     * @param string $expression Ekspresi jadwal (Misal: '0 0 * * *' untuk cron, '3600' untuk interval detik)
     * @param string $timezone Zona waktu (Misal: 'Asia/Jakarta')
     * @param bool $isActive Apakah jadwal ini sedang aktif
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $scheduleType,
        public readonly string $expression,
        public readonly string $timezone = 'UTC',
        public readonly bool $isActive = true
    ) {}
}
