<?php

namespace App\Services\Alerting\Models;

class AlertRequest
{
    /**
     * @param string $jobId ID Sinkronisasi yang dikaitkan dengan alarm
     */
    public function __construct(
        public readonly string $jobId
    ) {}
}
