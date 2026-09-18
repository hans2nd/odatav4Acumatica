<?php

namespace App\Services\Observability\Models;

class ObservabilityRecord
{
    /**
     * @param ExecutionTrace $trace Rangkaian jejak (trace) komplit
     * @param ObservabilityContext $context Konteks tempat jejak ini direkam
     */
    public function __construct(
        public readonly ExecutionTrace $trace,
        public readonly ObservabilityContext $context
    ) {}
}
