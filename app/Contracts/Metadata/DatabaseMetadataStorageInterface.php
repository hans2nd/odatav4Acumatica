<?php

namespace App\Contracts\Metadata;

use App\DTO\Metadata\MetadataEntity;

interface DatabaseMetadataStorageInterface
{
    public function save(MetadataEntity $metadataEntity, string $version, string $hash): void;
    
    public function get(string $entityKey): ?MetadataEntity;
    
    public function getVersion(string $entityKey): ?string;
    
    public function getHash(string $entityKey): ?string;
    
    public function logHistory(string $entityKey, ?string $oldVersion, string $newVersion, array $changes): void;
    
    public function logRefreshOperation(array $data): void;
    
    public function recordStatistics(string $entityKey, array $stats): void;
}
