<?php

namespace App\Services\Orchestration;

class SynchronizationExecutionRequest
{
    /**
     * @param string $jobId ID Unik pekerjaan sinkronisasi
     * @param string $entityName Nama entitas target sinkronisasi (Misal: 'Invoices')
     * @param array $options Opsi tambahan yang bisa memodifikasi perilaku orkestrator (seperti policy resume dll)
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $entityName,
        public readonly array $options = []
    ) {}
}
