<?php

namespace App\Services\Alerting\Contracts;

use App\Services\Alerting\Models\AlertDefinition;
use App\Services\Alerting\Models\NotificationMessage;
use App\Services\Alerting\Models\NotificationResult;

interface AlertDispatcherInterface
{
    /**
     * Menerima alarm yang meletus dan menyebarkannya ke seluruh Notification Provider yang sesuai.
     * 
     * @return array<NotificationResult>
     */
    public function dispatch(AlertDefinition $definition, NotificationMessage $message): array;
}
