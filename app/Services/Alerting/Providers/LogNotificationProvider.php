<?php

namespace App\Services\Alerting\Providers;

use App\Services\Alerting\Contracts\NotificationProviderInterface;
use App\Services\Alerting\Enums\NotificationChannel;
use App\Services\Alerting\Models\NotificationMessage;
use App\Services\Alerting\Models\NotificationResult;
use Illuminate\Support\Facades\Log;

class LogNotificationProvider implements NotificationProviderInterface
{
    public function supports(string $channel): bool
    {
        // Hanya merespon permintaan berchannel LOG atau fallback
        return $channel === NotificationChannel::LOG;
    }

    public function send(NotificationMessage $message): NotificationResult
    {
        $logText = "[ALARM: {$message->severity}] {$message->title} - {$message->body}";
        
        Log::warning($logText, $message->metadata);

        return new NotificationResult(true, 'LogNotificationProvider', 'Berhasil mencetak alarm ke file log sistem.');
    }
}
