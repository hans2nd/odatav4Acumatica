<?php

namespace App\Services\Metadata;

use App\Contracts\Metadata\MetadataSynchronizationServiceInterface;
use App\Contracts\Metadata\MetadataRepositoryInterface;
use App\DTO\Metadata\MetadataResult;

class MetadataSynchronizationService implements MetadataSynchronizationServiceInterface
{
    public function __construct(
        private MetadataRepositoryInterface $repository
    ) {}

    public function getMetadata(string $entityKey, bool $forceRefresh = false): MetadataResult
    {
        return $this->repository->getMetadata($entityKey, $forceRefresh);
    }

    public function synchronize(string $entityKey): MetadataResult
    {
        // Force refresh triggers the provider discovery through the repository
        return $this->repository->getMetadata($entityKey, true);
    }

    public function invalidate(string $entityKey): void
    {
        $this->repository->invalidate($entityKey);
    }
}
