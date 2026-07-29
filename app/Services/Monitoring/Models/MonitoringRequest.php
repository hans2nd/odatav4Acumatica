<?php

namespace App\Services\Monitoring\Models;

class MonitoringRequest
{
    /**
     * @param string $jobId Identifier pekerjaan sinkronisasi yang akan dipantau
     */
    public function __construct(
        public readonly string $jobId
    ) {}
}
