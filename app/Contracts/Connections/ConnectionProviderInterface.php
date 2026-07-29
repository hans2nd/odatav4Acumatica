<?php

declare(strict_types=1);

namespace App\Contracts\Connections;

use App\DTO\Connections\ConfigurationField;
use App\DTO\Connections\ConnectionHealth;
use App\DTO\Connections\MetadataNode;
use App\DTO\Connections\MetadataTree;
use App\DTO\Connections\ProviderInfo;

interface ConnectionProviderInterface
{
    /**
     * Returns an array of ConfigurationField definitions required to configure this provider.
     * 
     * @return ConfigurationField[]
     */
    public function getConfigurationSchema(): array;

    /**
     * Validates configuration rules.
     */
    public function getValidationRules(): array;

    /**
     * Attempts a physical/network connection and returns standardized health states.
     */
    public function testConnection(array $credentials): ConnectionHealth;

    /**
     * Returns diagnostic info (e.g., Driver version, API version).
     */
    public function getDiagnosticInfo(array $credentials): ProviderInfo;

    /**
     * Discovers all available objects formatted into a normalized Metadata Tree.
     */
    public function discoverMetadata(array $credentials): MetadataTree;

    /**
     * Discovers specific structure (columns, properties, schemas) of a specific object.
     */
    public function discoverStructure(array $credentials, string $objectName): MetadataNode;

    /**
     * Yields the raw underlying connection client (e.g., PDO, HTTP Client).
     */
    public function createRuntimeConnection(array $credentials): mixed;

    /**
     * Returns a list of supported capabilities for this specific provider.
     * 
     * @return string[]
     */
    public function getCapabilities(): array;
}
