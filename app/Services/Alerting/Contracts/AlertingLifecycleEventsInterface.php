<?php

namespace App\Services\Alerting\Contracts;

use App\Services\Alerting\Models\AlertRequest;
use App\Services\Alerting\Models\AlertContext;
use App\Services\Alerting\Models\AlertResult;
use Throwable;

interface AlertingLifecycleEventsInterface
{
    public function beforeAlertEvaluation(AlertRequest $request, AlertContext $context): void;
    public function afterAlertEvaluation(AlertResult $result): void;
    public function beforeNotification(AlertRequest $request): void;
    public function afterNotification(array $notificationResults): void;
    public function onAlertFailure(Throwable $exception): void;
    public function onAlertCompleted(AlertResult $result): void;
}
