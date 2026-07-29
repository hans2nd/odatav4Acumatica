<?php

namespace App\Services\Persistence;

class ConflictResolutionResult
{
    /**
     * @param bool $isResolved
     * @param array|null $resolvedRecord Data pemenang yang berhak disimpan
     * @param string $decision Contoh: 'SOURCE_SELECTED', 'DESTINATION_SELECTED', 'SKIPPED'
     * @param \Throwable|null $error
     */
    public function __construct(
        public readonly bool $isResolved,
        public readonly ?array $resolvedRecord,
        public readonly string $decision,
        public readonly ?\Throwable $error = null
    ) {}
}
