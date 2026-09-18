<?php

namespace App\Services\Metadata;

use App\Contracts\Metadata\MetadataStoreInterface;
use App\Contracts\Metadata\MetadataCacheInterface;
use App\Contracts\Metadata\MetadataRepositoryInterface;
use App\DTO\Metadata\MetadataEntity;

class MetadataStore implements MetadataStoreInterface
{
    public function __construct(
        private MetadataCacheInterface $cache,
        private MetadataRepositoryInterface $repository
    ) {}

    public function get(string $entityKey): ?MetadataEntity
    {
        // Check Cache first
        $cached = $this->cache->get($entityKey);
        if ($cached) {
            try { $this->repository->recordStatistics($entityKey, ['cache_hits' => 1]); } catch (\Exception $e) {}
            return $cached;
        }

        try { $this->repository->recordStatistics($entityKey, ['cache_misses' => 1]); } catch (\Exception $e) {}

        // Fallback to Database
        try {
            $metadata = $this->repository->get($entityKey);
            if ($metadata) {
                // Warm up cache
                $this->cache->set($entityKey, $metadata);
            }
            return $metadata;
        } catch (\Exception $e) {
            return null; // Gracefully fallback to provider if DB fails
        }
    }
    
    public function save(MetadataEntity $metadataEntity): void
    {
        // 1. Generate version hashes
        $hash = $metadataEntity->getHash();
        $version = 'v_' . substr($hash, 0, 12);
        
        $oldVersion = $this->repository->getVersion($metadataEntity->entityKey);

        // 2. Persist to DB
        try {
            $this->repository->save($metadataEntity, $version, $hash);
            
            if ($oldVersion !== $version) {
                $this->repository->logHistory($metadataEntity->entityKey, $oldVersion, $version, [
                    'reason' => 'Schema changed or initialized'
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("MetadataStore DB Save failed: " . $e->getMessage());
        }

        // 3. Update Cache
        $this->cache->set($metadataEntity->entityKey, $metadataEntity);
    }
    
    public function invalidate(string $entityKey): void
    {
        $this->cache->forget($entityKey);
    }
}
