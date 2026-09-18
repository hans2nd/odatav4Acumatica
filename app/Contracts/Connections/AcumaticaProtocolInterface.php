<?php

declare(strict_types=1);

namespace App\Contracts\Connections;

use App\DTO\Connections\ConnectionHealth;
use App\DTO\Connections\MetadataNode;
use App\DTO\Connections\MetadataTree;
use App\DTO\Connections\ProviderInfo;
use Illuminate\Http\Client\PendingRequest;

interface AcumaticaProtocolInterface
{
    /**
     * Attempts a physical/network connection and returns standardized health states.
     */
    public function testConnection(array $credentials, string $runtimeUrl): ConnectionHealth;

    /**
     * Returns diagnostic info.
     */
    public function getDiagnosticInfo(array $credentials, string $runtimeUrl): ProviderInfo;

    /**
     * Discovers all available objects formatted into a normalized Metadata Tree.
     */
    public function discoverMetadata(array $credentials, string $runtimeUrl): MetadataTree;

    /**
     * Discovers specific structure (columns, properties, schemas) of a specific object.
     */
    public function discoverStructure(array $credentials, string $runtimeUrl, string $objectName): MetadataNode;

    /**
     * Yields the raw underlying connection client.
     */
    public function createRuntimeConnection(array $credentials, string $runtimeUrl): PendingRequest;

    /**
     * Returns a list of supported capabilities for this specific protocol.
     * 
     * @return string[]
     */
    public function getCapabilities(): array;
}
