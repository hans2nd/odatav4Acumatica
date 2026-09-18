<?php

namespace App\Contracts\Metadata;

use App\DTO\Metadata\MetadataEntity;

interface MetadataStoreInterface
{
    /**
     * Gets metadata from Cache first, then DB.
     */
    public function get(string $entityKey): ?MetadataEntity;
    
    /**
     * Saves metadata to DB and updates Cache.
     */
    public function save(MetadataEntity $metadataEntity): void;
    
    /**
     * Invalidates metadata in Cache.
     */
    public function invalidate(string $entityKey): void;
}
