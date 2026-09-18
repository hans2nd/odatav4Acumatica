<?php

namespace App\Contracts\Metadata;

use App\DTO\Metadata\MetadataResult;

interface MetadataRepositoryInterface
{
    /**
     * Gets metadata for an entity. Orchestrates Cache, Database, and Provider.
     */
    public function getMetadata(string $entityKey, bool $forceRefresh = false): MetadataResult;

    /**
     * Gets metadata for a specific database table (for Query/Report Builders).
     */
    public function getTableMetadata(string $connectionKey, string $table, bool $forceRefresh = false): MetadataResult;

    /**
     * Invalidates the cache for an entity.
     */
    public function invalidate(string $entityKey): void;
}
