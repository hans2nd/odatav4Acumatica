<?php

namespace App\Services\Alerting\Models;

class AlertDefinition
{
    /**
     * @param string $id Identifier unik definisi alarm
     * @param string $severity Tingkat keparahan (Lihat AlertSeverity)
     * @param array<string> $channels Daftar saluran tujuan notifikasi (Lihat NotificationChannel)
     * @param string $title Judul dasar alarm
     */
    public function __construct(
        public readonly string $id,
        public readonly string $severity,
        public readonly array $channels,
        public readonly string $title
    ) {}
}
