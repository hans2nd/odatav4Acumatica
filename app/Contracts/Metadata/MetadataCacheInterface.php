<?php

namespace App\Contracts\Metadata;

use App\DTO\Metadata\MetadataEntity;

interface MetadataCacheInterface
{
    public function get(string $entityKey): ?MetadataEntity;
    
    public function set(string $entityKey, MetadataEntity $metadata, int $ttlSeconds = 604800): void; // 7 days default
    
    public function forget(string $entityKey): void;
}
