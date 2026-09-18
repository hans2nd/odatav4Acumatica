<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\Metadata\MetadataSynchronizationServiceInterface;
use App\Contracts\Metadata\MetadataRepositoryInterface;
use App\Contracts\Metadata\DatabaseMetadataStorageInterface;
use App\Contracts\Metadata\MetadataCacheInterface;
use App\Services\Metadata\MetadataSynchronizationService;
use App\Services\Metadata\MetadataRepository;
use App\Services\Metadata\DatabaseMetadataStorage;
use App\Services\Metadata\MetadataCache;

class MetadataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MetadataCacheInterface::class, MetadataCache::class);
        $this->app->bind(DatabaseMetadataStorageInterface::class, DatabaseMetadataStorage::class);
        $this->app->bind(MetadataRepositoryInterface::class, MetadataRepository::class);
        $this->app->bind(MetadataSynchronizationServiceInterface::class, MetadataSynchronizationService::class);
    }

    public function boot(): void
    {
        //
    }
}
