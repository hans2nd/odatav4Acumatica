<?php

namespace App\Services\Persistence;

class PersistenceCapabilities
{
    public function __construct(
        public readonly bool $supportsBulkUpsert,
        public readonly bool $supportsNativeMerge,
        public readonly bool $supportsOnDuplicateKeyUpdate,
        public readonly bool $supportsOnConflict,
        public readonly bool $supportsReturning,
        public readonly bool $supportsTransactions,
        public readonly bool $supportsSavepoints,
        public readonly int $maxBatchSize,
        public readonly string $identifierQuoteChar
    ) {}
}
