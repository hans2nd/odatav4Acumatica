<?php

namespace App\Services\Reliability\Classification;

class FailureContext
{
    /**
     * @param string $entityName Nama entitas (misal: Invoices, Customers)
     * @param string $operationName Nama operasi (misal: Upsert, Delete)
     * @param array $additionalContext Metadata kontekstual tambahan
     */
    public function __construct(
        public readonly string $entityName = 'UNKNOWN',
        public readonly string $operationName = 'UNKNOWN',
        public readonly array $additionalContext = []
    ) {}
}
