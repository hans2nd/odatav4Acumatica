<?php

namespace App\Services\Metadata;

use App\Contracts\Metadata\MetadataCacheInterface;
use App\DTO\Metadata\MetadataEntity;
use Illuminate\Support\Facades\Cache;

class MetadataCache implements MetadataCacheInterface
{
    private array $memoryCache = [];
    
    private function getCacheKey(string $entityKey): string
    {
        return 'sync_metadata_' . $entityKey;
    }

    public function get(string $entityKey): ?MetadataEntity
    {
        // Level 1: Memory Cache
        if (isset($this->memoryCache[$entityKey])) {
            return $this->memoryCache[$entityKey];
        }

        // Level 2: Laravel Cache (Redis/File)
        $cacheKey = $this->getCacheKey($entityKey);
        $cached = Cache::get($cacheKey);

        if ($cached instanceof MetadataEntity) {
            $this->memoryCache[$entityKey] = $cached;
            return $cached;
        }

        return null;
    }

    public function set(string $entityKey, MetadataEntity $metadata, int $ttlSeconds = 604800): void
    {
        // Set Memory Cache
        $this->memoryCache[$entityKey] = $metadata;

        // Set Laravel Cache
        Cache::put($this->getCacheKey($entityKey), $metadata, $ttlSeconds);
    }

    public function forget(string $entityKey): void
    {
        unset($this->memoryCache[$entityKey]);
        Cache::forget($this->getCacheKey($entityKey));
    }
}
