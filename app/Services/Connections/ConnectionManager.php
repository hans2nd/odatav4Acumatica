<?php

declare(strict_types=1);

namespace App\Services\Connections;

use App\Contracts\Connections\ConnectionProviderInterface;
use App\DTO\Connections\ConnectionHealth;
use App\DTO\Connections\MetadataNode;
use App\DTO\Connections\MetadataTree;
use App\DTO\Connections\ProviderInfo;
use App\Models\Connection;
use InvalidArgumentException;
use RuntimeException;

class ConnectionManager
{
    /**
     * Cache of runtime connections for the current request lifecycle.
     * 
     * @var array<string, mixed>
     */
    private array $runtimeConnections = [];

    public function __construct(
        private readonly ConnectionProviderFactory $factory
    ) {}

    /**
     * Retrieves the configured provider for a given connection key.
     */
    public function getProvider(string $connectionKey): ConnectionProviderInterface
    {
        return $this->providerForConnection($connectionKey);
    }

    /**
     * Resolves a connection by key, determines its type, and instantiates the provider.
     */
    public function providerForConnection(string $connectionKey): ConnectionProviderInterface
    {
        $connection = $this->getConnectionModel($connectionKey);
        return $this->factory->make($connection->type);
    }

    /**
     * Creates and caches a runtime connection for the given connection key.
     */
    public function getRuntimeConnection(string $connectionKey): mixed
    {
        if (isset($this->runtimeConnections[$connectionKey])) {
            return $this->runtimeConnections[$connectionKey];
        }

        $connection = $this->getConnectionModel($connectionKey);
        $provider = $this->factory->make($connection->type);
        
        $runtime = $provider->createRuntimeConnection($connection->credentials ?? []);
        
        $this->runtimeConnections[$connectionKey] = $runtime;

        return $runtime;
    }

    public function testConnection(string $type, array $credentials, ?string $connectionKey = null): ConnectionHealth
    {
        if ($connectionKey && empty($credentials)) {
            $connection = $this->getConnectionModel($connectionKey);
            $type = $connection->type;
            $credentials = $connection->credentials ?? [];
        }

        $provider = $this->factory->make($type);
        return $provider->testConnection($credentials);
    }

    public function runDiagnostics(string $type, array $credentials, ?string $connectionKey = null): ConnectionHealth
    {
        if ($connectionKey && empty($credentials)) {
            $connection = $this->getConnectionModel($connectionKey);
            $type = $connection->type;
            $credentials = $connection->credentials ?? [];
        }

        $provider = $this->factory->make($type);
        
        if (method_exists($provider, 'runDiagnostics')) {
            return $provider->runDiagnostics($credentials);
        }

        // Fallback for providers that haven't implemented full diagnostics yet
        return $provider->testConnection($credentials);
    }

    /**
     * Discovers all available objects for a connection.
     */
    public function discoverMetadata(string $connectionKey): MetadataTree
    {
        $connection = $this->getConnectionModel($connectionKey);
        $provider = $this->factory->make($connection->type);
        
        $cacheKey = "connection_{$connectionKey}_metadata";
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHours(24), function () use ($provider, $connection) {
            return $provider->discoverMetadata($connection->credentials ?? []);
        });
    }

    /**
     * Discovers the internal structure of a specific object within a connection.
     */
    public function discoverStructure(string $connectionKey, string $objectName): MetadataNode
    {
        $connection = $this->getConnectionModel($connectionKey);
        $provider = $this->factory->make($connection->type);
        
        $cacheKey = "connection_{$connectionKey}_structure_" . md5($objectName);
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHours(24), function () use ($provider, $connection, $objectName) {
            return $provider->discoverStructure($connection->credentials ?? [], $objectName);
        });
    }

    /**
     * Gets diagnostic info for a connection.
     */
    public function getDiagnosticInfo(string $connectionKey): ProviderInfo
    {
        $connection = $this->getConnectionModel($connectionKey);
        $provider = $this->factory->make($connection->type);
        
        return $provider->getDiagnosticInfo($connection->credentials ?? []);
    }

    /**
     * Gets capabilities for a specific connection type.
     */
    public function getCapabilities(string $type): array
    {
        $provider = $this->factory->make($type);
        return $provider->getCapabilities();
    }

    /**
     * Retrieves the UI configuration schema for a specific connection type.
     */
    public function getConfigurationSchema(string $type): array
    {
        $provider = $this->factory->make($type);
        return $provider->getConfigurationSchema();
    }

    /**
     * Helper to retrieve the connection model.
     */
    private function getConnectionModel(string $connectionKey): Connection
    {
        $connection = Connection::where('key', $connectionKey)->first();

        if (!$connection) {
            throw new RuntimeException("Connection [{$connectionKey}] not found in database.");
        }

        return $connection;
    }
}
