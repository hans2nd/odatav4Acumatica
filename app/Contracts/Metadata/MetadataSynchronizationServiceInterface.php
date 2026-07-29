<?php

namespace App\Contracts\Metadata;

use App\DTO\Metadata\MetadataResult;

interface MetadataSynchronizationServiceInterface
{
    /**
     * Get metadata for an entity. Will use cache -> DB -> provider hierarchy.
     */
    public function getMetadata(string $entityKey, bool $forceRefresh = false): MetadataResult;

    /**
     * Force synchronization of metadata for an entity from provider.
     */
    public function synchronize(string $entityKey): MetadataResult;
    
    /**
     * Invalidate cached metadata for an entity.
     */
    public function invalidate(string $entityKey): void;
}
