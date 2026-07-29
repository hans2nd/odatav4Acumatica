<?php

namespace App\Services\Alerting\Models;

class NotificationMessage
{
    /**
     * @param string $title Judul notifikasi
     * @param string $body Konten/Pesan utama
     * @param string $severity Keparahan pesan
     * @param array $metadata Informasi tambahan (Misalnya detail error atau Job ID)
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $severity,
        public readonly array $metadata = []
    ) {}
}
