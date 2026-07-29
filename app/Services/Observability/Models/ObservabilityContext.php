<?php

namespace App\Services\Observability\Models;

class ObservabilityContext
{
    /**
     * @param string $serviceName Nama layanan/modul yang melaporkan (Misal: 'SynchronizationEngine')
     * @param ExecutionMetadata|null $metadata Metadata server (Opsional)
     */
    public function __construct(
        public readonly string $serviceName = 'SynchronizationEngine',
        public readonly ?ExecutionMetadata $metadata = null
    ) {}
}
