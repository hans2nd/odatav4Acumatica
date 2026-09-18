<?php

declare(strict_types=1);

namespace App\Services\Connections\Providers;

use App\Contracts\Connections\ConnectionProviderInterface;
use App\DTO\Connections\ConfigurationField;
use App\DTO\Connections\ConnectionHealth;
use App\DTO\Connections\ConnectionHealthStatus;
use App\DTO\Connections\MetadataNode;
use App\DTO\Connections\MetadataTree;
use App\DTO\Connections\ProviderInfo;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class LocalEnvironmentProvider implements ConnectionProviderInterface
{
    public function getConfigurationSchema(): array
    {
        return [
            new ConfigurationField(
                field: 'info',
                label: 'Information',
                type: 'text',
                helpText: 'This connection is linked directly to your .env file and cannot be modified here.'
            )
        ];
    }

    public function getValidationRules(): array
    {
        return [];
    }

    public function testConnection(array $credentials): ConnectionHealth
    {
        $start = microtime(true);
        try {
            DB::connection()->getPdo();
            $elapsed = (int) round((microtime(true) - $start) * 1000);
            return new ConnectionHealth(ConnectionHealthStatus::HEALTHY, 'Connected successfully', $elapsed);
        } catch (Throwable $e) {
            return new ConnectionHealth(ConnectionHealthStatus::ERROR_UNREACHABLE, $e->getMessage());
        }
    }

    public function getDiagnosticInfo(array $credentials): ProviderInfo
    {
        $driver = config('database.default');
        return new ProviderInfo(
            name: 'Local Environment',
            driverVersion: $driver,
            serverVersion: 'System Configured',
            protocolVersion: 'Local'
        );
    }

    public function discoverMetadata(array $credentials): MetadataTree
    {
        $tables = DB::connection()->getSchemaBuilder()->getTableListing();
        $nodes = [];

        foreach ($tables as $table) {
            $nodes[] = new MetadataNode(name: $table, type: 'table');
        }

        return new MetadataTree($nodes);
    }

    public function discoverStructure(array $credentials, string $objectName): MetadataNode
    {
        $columns = DB::connection()->getSchemaBuilder()->getColumnListing($objectName);
        return new MetadataNode(name: $objectName, type: 'table', properties: $columns);
    }

    public function createRuntimeConnection(array $credentials): PDO
    {
        return DB::connection()->getPdo();
    }

    public function getCapabilities(): array
    {
        return ['metadata', 'query', 'transaction', 'views', 'stored_procedures'];
    }
}
