<?php

namespace App\Services\Alerting\Contracts;

use App\Services\Alerting\Models\NotificationMessage;
use App\Services\Alerting\Models\NotificationResult;

interface NotificationProviderInterface
{
    /**
     * Apakah provider ini mendukung channel yang diminta?
     */
    public function supports(string $channel): bool;

    /**
     * Mengirim pesan notifikasi ke sistem eksternal
     */
    public function send(NotificationMessage $message): NotificationResult;
}
