<?php

namespace App\Services\Metadata;

use App\Contracts\Metadata\MetadataRepositoryInterface;
use App\Contracts\Metadata\MetadataCacheInterface;
use App\Contracts\Metadata\DatabaseMetadataStorageInterface;
use App\Services\Connections\ConnectionManager;
use App\Services\EntityConfigService;
use App\DTO\Metadata\MetadataResult;
use App\DTO\Metadata\MetadataEntity;
use App\DTO\Metadata\MetadataField;
use App\Enums\MetadataStatus;
use App\Enums\RefreshReason;
use App\Enums\MetadataSource;
use Illuminate\Support\Facades\Log;

class MetadataRepository implements MetadataRepositoryInterface
{
    public function __construct(
        private MetadataCacheInterface $cache,
        private DatabaseMetadataStorageInterface $databaseStore,
        private ConnectionManager $connectionManager,
        private EntityConfigService $entityConfigService
    ) {}

    public function getMetadata(string $entityKey, bool $forceRefresh = false, ?RefreshReason $reason = null): MetadataResult
    {
        $startTime = microtime(true);
        $source = MetadataSource::CACHE;
        $stats = ['cache_hits' => 0, 'cache_misses' => 0, 'provider_calls' => 0, 'refresh_count' => 0, 'validation_errors' => 0];
        
        $cacheDuration = 0;
        $dbDuration = 0;
        $providerNetworkMs = 0;
        $providerProcessingMs = 0;
        
        $success = false;
        $errorMessage = null;
        $previousHash = $this->databaseStore->getHash($entityKey);
        $currentHash = $previousHash;
        $metadataEntity = null;

        $refreshReason = $reason ?? ($forceRefresh ? RefreshReason::FORCE_REFRESH : RefreshReason::AUTO);

        try {
            $tCacheStart = microtime(true);
            if ($forceRefresh) {
                $stats['refresh_count'] = 1;
                $this->cache->forget($entityKey);
                $metadataEntity = null;
            } else {
                $metadataEntity = $this->cache->get($entityKey);
                if ($metadataEntity) {
                    $stats['cache_hits'] = 1;
                }
            }
            $cacheDuration = round((microtime(true) - $tCacheStart) * 1000);

            if (!$metadataEntity && !$forceRefresh) {
                $stats['cache_misses'] = 1;
                $refreshReason = $reason ?? RefreshReason::CACHE_MISS;
                
                $tDbStart = microtime(true);
                $metadataEntity = $this->databaseStore->get($entityKey);
                $dbDuration = round((microtime(true) - $tDbStart) * 1000);
                
                if ($metadataEntity) {
                    $source = MetadataSource::DATABASE;
                    $this->cache->set($entityKey, $metadataEntity);
                }
            }

            if (!$metadataEntity) {
                $source = MetadataSource::PROVIDER;
                $stats['provider_calls'] = 1;
                
                $tProvNetStart = microtime(true);
                $metadataNode = null;
                
                $config = $this->entityConfigService->get($entityKey);
                if (!$config) {
                    throw new \RuntimeException("Entity configuration not found for: {$entityKey}");
                }

                $sourceConnName = $config['source_connection'] ?? 'acumatica_default';
                $odataEntity = $config['odata_entity'];
                
                // Use the newly dedicated method and discover structure through the ConnectionManager
                $metadataNode = $this->connectionManager->discoverStructure($sourceConnName, $odataEntity);
                $providerNetworkMs = round((microtime(true) - $tProvNetStart) * 1000);
                
                $tProvProcStart = microtime(true);
                $fields = [];
                if (!empty($metadataNode->properties)) {
                    foreach ($metadataNode->properties as $prop) {
                        $propName = is_array($prop) ? ($prop['name'] ?? $prop['Field'] ?? '') : (string)$prop;
                        if (!$propName) continue;
                        $fields[] = new MetadataField(
                            name: $propName,
                            dataType: 'string',
                            isPrimaryKey: (strcasecmp($propName, 'id') === 0 || preg_match('/ID$/i', $propName))
                        );
                    }
                }
                
                $metadataEntity = new MetadataEntity(
                    entityKey: $entityKey,
                    fields: $fields,
                    providerVersion: 'ProviderDiscovery'
                );
                
                $currentHash = $metadataEntity->getHash();
                $version = 'v_' . substr($currentHash, 0, 12);
                $providerProcessingMs = round((microtime(true) - $tProvProcStart) * 1000);
                
                $tDbSaveStart = microtime(true);
                if ($previousHash !== $currentHash) {
                    $this->databaseStore->save($metadataEntity, $version, $currentHash);
                    
                    $oldVersion = $previousHash ? 'v_' . substr($previousHash, 0, 12) : null;
                    $this->databaseStore->logHistory($entityKey, $oldVersion, $version, ['reason' => 'Schema changed or initialized']);
                }
                $dbDuration += round((microtime(true) - $tDbSaveStart) * 1000);
                
                $tCacheSetStart = microtime(true);
                $this->cache->set($entityKey, $metadataEntity);
                $cacheDuration += round((microtime(true) - $tCacheSetStart) * 1000);
            }
            
            if (!$metadataEntity) {
                throw new \Exception("Failed to fetch metadata.");
            }

            $success = true;
            $stats['discovery_time_ms'] = round((microtime(true) - $startTime) * 1000);
            $this->databaseStore->recordStatistics($entityKey, $stats);

        } catch (\Exception $e) {
            $success = false;
            $errorMessage = $e->getMessage();
            Log::error("MetadataRepository Error: " . $errorMessage);
        }

        $totalDurationMs = round((microtime(true) - $startTime) * 1000);

        if ($source === MetadataSource::PROVIDER || $forceRefresh) {
            $this->databaseStore->logRefreshOperation([
                'entity' => $entityKey,
                'provider' => $config['source_connection'] ?? 'unknown',
                'refresh_reason' => $refreshReason->value,
                'cache_key' => 'sync_metadata_' . $entityKey,
                'started_at' => date('Y-m-d H:i:s', (int)$startTime),
                'finished_at' => now(),
                'duration_ms' => $totalDurationMs,
                'cache_duration_ms' => $cacheDuration,
                'database_duration_ms' => $dbDuration,
                'provider_network_ms' => $providerNetworkMs,
                'provider_processing_ms' => $providerProcessingMs,
                'success' => $success,
                'status' => $success ? MetadataStatus::READY->value : MetadataStatus::FAILED->value,
                'error_message' => $errorMessage,
                'previous_hash' => $previousHash,
                'current_hash' => $currentHash,
                'metadata_source' => $source->value,
            ]);
        }

        if (!$success) {
            return new MetadataResult($entityKey, [], '', '', 'Error', false, $errorMessage);
        }

        return new MetadataResult(
            entityKey: $entityKey,
            fields: array_map(fn($f) => $f->toArray(), $metadataEntity->fields),
            version: 'v_' . substr($currentHash, 0, 12),
            hash: $currentHash,
            source: $source->value
        );
    }

    public function invalidate(string $entityKey): void
    {
        $this->cache->forget($entityKey);
    }

    public function getTableMetadata(string $connectionKey, string $table, bool $forceRefresh = false): MetadataResult
    {
        $virtualEntityKey = "table_{$connectionKey}_{$table}";
        
        $startTime = microtime(true);
        $source = MetadataSource::CACHE;
        $stats = ['cache_hits' => 0, 'cache_misses' => 0, 'provider_calls' => 0, 'refresh_count' => 0, 'validation_errors' => 0];
        
        $cacheDuration = 0;
        $dbDuration = 0;
        $providerNetworkMs = 0;
        $providerProcessingMs = 0;
        
        $success = false;
        $errorMessage = null;
        $previousHash = $this->databaseStore->getHash($virtualEntityKey);
        $currentHash = $previousHash;
        $metadataEntity = null;

        $refreshReason = $forceRefresh ? RefreshReason::FORCE_REFRESH : RefreshReason::AUTO;

        try {
            $tCacheStart = microtime(true);
            if ($forceRefresh) {
                $stats['refresh_count'] = 1;
                $this->cache->forget($virtualEntityKey);
                $metadataEntity = null;
            } else {
                $metadataEntity = $this->cache->get($virtualEntityKey);
                if ($metadataEntity) {
                    $stats['cache_hits'] = 1;
                }
            }
            $cacheDuration = round((microtime(true) - $tCacheStart) * 1000);

            if (!$metadataEntity && !$forceRefresh) {
                $stats['cache_misses'] = 1;
                $refreshReason = RefreshReason::CACHE_MISS;
                
                $tDbStart = microtime(true);
                $metadataEntity = $this->databaseStore->get($virtualEntityKey);
                $dbDuration = round((microtime(true) - $tDbStart) * 1000);
                
                if ($metadataEntity) {
                    $source = MetadataSource::DATABASE;
                    $this->cache->set($virtualEntityKey, $metadataEntity);
                }
            }

            if (!$metadataEntity) {
                $source = MetadataSource::PROVIDER;
                $stats['provider_calls'] = 1;
                
                $tProvNetStart = microtime(true);
                $metadataNode = $this->connectionManager->discoverStructure($connectionKey, $table);
                $providerNetworkMs = round((microtime(true) - $tProvNetStart) * 1000);
                
                $tProvProcStart = microtime(true);
                $fields = [];
                if (!empty($metadataNode->properties)) {
                    foreach ($metadataNode->properties as $prop) {
                        $propName = is_array($prop) ? ($prop['name'] ?? $prop['Field'] ?? '') : (string)$prop;
                        if (!$propName) continue;
                        $fields[] = new MetadataField(
                            name: $propName,
                            dataType: 'string',
                            isPrimaryKey: (strcasecmp($propName, 'id') === 0 || preg_match('/ID$/i', $propName))
                        );
                    }
                }
                
                $metadataEntity = new MetadataEntity(
                    entityKey: $virtualEntityKey,
                    fields: $fields,
                    providerVersion: 'ProviderDiscovery'
                );
                
                $currentHash = $metadataEntity->getHash();
                $version = 'v_' . substr($currentHash, 0, 12);
                $providerProcessingMs = round((microtime(true) - $tProvProcStart) * 1000);
                
                $tDbSaveStart = microtime(true);
                if ($previousHash !== $currentHash) {
                    $this->databaseStore->save($metadataEntity, $version, $currentHash);
                    
                    $oldVersion = $previousHash ? 'v_' . substr($previousHash, 0, 12) : null;
                    $this->databaseStore->logHistory($virtualEntityKey, $oldVersion, $version, ['reason' => 'Schema changed or initialized']);
                }
                $dbDuration += round((microtime(true) - $tDbSaveStart) * 1000);
                
                $tCacheSetStart = microtime(true);
                $this->cache->set($virtualEntityKey, $metadataEntity);
                $cacheDuration += round((microtime(true) - $tCacheSetStart) * 1000);
            }
            
            if (!$metadataEntity) {
                throw new \Exception("Failed to fetch metadata.");
            }

            $success = true;
            $stats['discovery_time_ms'] = round((microtime(true) - $startTime) * 1000);
            $this->databaseStore->recordStatistics($virtualEntityKey, $stats);

        } catch (\Exception $e) {
            $success = false;
            $errorMessage = $e->getMessage();
            Log::error("MetadataRepository Table Error: " . $errorMessage);
        }

        $totalDurationMs = round((microtime(true) - $startTime) * 1000);

        if ($source === MetadataSource::PROVIDER || $forceRefresh) {
            $this->databaseStore->logRefreshOperation([
                'entity' => $virtualEntityKey,
                'provider' => $connectionKey,
                'refresh_reason' => $refreshReason->value,
                'cache_key' => 'sync_metadata_' . $virtualEntityKey,
                'started_at' => date('Y-m-d H:i:s', (int)$startTime),
                'finished_at' => now(),
                'duration_ms' => $totalDurationMs,
                'cache_duration_ms' => $cacheDuration,
                'database_duration_ms' => $dbDuration,
                'provider_network_ms' => $providerNetworkMs,
                'provider_processing_ms' => $providerProcessingMs,
                'success' => $success,
                'status' => $success ? MetadataStatus::READY->value : MetadataStatus::FAILED->value,
                'error_message' => $errorMessage,
                'previous_hash' => $previousHash,
                'current_hash' => $currentHash,
                'metadata_source' => $source->value,
            ]);
        }

        if (!$success) {
            return new MetadataResult($virtualEntityKey, [], '', '', 'Error', false, $errorMessage);
        }

        return new MetadataResult(
            entityKey: $virtualEntityKey,
            fields: array_map(fn($f) => $f->toArray(), $metadataEntity->fields),
            version: 'v_' . substr($currentHash, 0, 12),
            hash: $currentHash,
            source: $source->value
        );
    }
}
