<?php

namespace App\Services\Alerting\Models;

class NotificationResult
{
    /**
     * @param bool $success Apakah notifikasi sukses dikirim ke layanan eksternal
     * @param string $providerName Nama provider pengirim (Misal: 'SlackProvider')
     * @param string $message Keterangan hasil pengiriman
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $providerName,
        public readonly string $message = ''
    ) {}
}
