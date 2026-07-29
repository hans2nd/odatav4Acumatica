<?php

namespace App\Services\Queries\Contracts;

use App\Services\Queries\QueryDefinition;

interface IncrementalQueryBuilderInterface
{
    /**
     * Apply a composite watermark filter based on the last successful sync state.
     */
    public function withCompositeWatermark(string $timestampField, string $pkField, ?string $lastTimestamp, ?string $lastPk): self;

    /**
     * Apply deterministic ordering (Timestamp ASC, PK ASC).
     */
    public function withOrdering(string $timestampField, string $pkField, string $direction = 'asc'): self;

    /**
     * Apply a batch/page size limit.
     */
    public function withLimit(int $limit): self;

    /**
     * Merge user-defined custom filters using logical AND.
     */
    public function withCustomFilters(array $filters): self;

    /**
     * Produce the final immutable query definition.
     */
    public function build(): QueryDefinition;
}
