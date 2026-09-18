<?php

declare(strict_types=1);

namespace App\Services\Connections\Providers;

use App\Contracts\Connections\AcumaticaProtocolInterface;
use App\Contracts\Connections\ConnectionProviderInterface;
use App\DTO\Connections\ConfigurationField;
use App\DTO\Connections\ConnectionHealth;
use App\DTO\Connections\MetadataNode;
use App\DTO\Connections\MetadataTree;
use App\DTO\Connections\ProviderInfo;
use App\Services\Connections\Providers\Adapters\ODataV3Adapter;
use App\Services\Connections\Providers\Adapters\ODataV4Adapter;

class AcumaticaProvider implements ConnectionProviderInterface
{
    public function getConfigurationSchema(): array
    {
        return [
            new ConfigurationField('base_url', 'Base URL', 'text', true, null, 'https://erp.panganlestari.com', 'The base URL without /odata or /odatav4'),
            new ConfigurationField('tenant', 'Tenant', 'text', true, null, 'PL'),
            new ConfigurationField('company', 'Company', 'text', false),
            new ConfigurationField('username', 'Username', 'text', true),
            new ConfigurationField('password', 'Password', 'password', true),
            new ConfigurationField('protocol', 'Protocol', 'select', true, [
                'odata_v3' => 'OData v3 (Generic Inquiry)',
                'odata_v4' => 'OData v4 (DAC)',
            ], 'odata_v4'),
            new ConfigurationField('odata_url', 'Exact OData Base URL (Override)', 'text', false, null, '', 'Optional. Leave blank to auto-generate. If filled, this exact URL will be used (e.g. https://erp.com/PL/odatav4).'),
        ];
    }

    public function getValidationRules(): array
    {
        return [
            'credentials.base_url' => 'required|url',
            'credentials.tenant'   => 'required|string',
            'credentials.company'  => 'nullable|string',
            'credentials.username' => 'required|string',
            'credentials.password' => 'required|string',
            'credentials.protocol' => 'required|in:odata_v3,odata_v4',
        ];
    }

    /**
     * Resolves the correct protocol adapter based on configuration.
     */
    protected function resolveAdapter(array $credentials): AcumaticaProtocolInterface
    {
        $protocol = $credentials['protocol'] ?? 'odata_v4';

        if ($protocol === 'odata_v3' || $protocol === 'v3') {
            return new ODataV3Adapter();
        }

        return new ODataV4Adapter();
    }

    /**
     * Automatically constructs the runtime URL for Acumatica.
     */
    protected function constructRuntimeUrl(array $credentials): string
    {
        $baseUrl = rtrim($credentials['base_url'] ?? '', '/');
        $tenant = trim($credentials['tenant'] ?? '', '/');
        $protocol = $credentials['protocol'] ?? 'odata_v4';

        $endpoint = ($protocol === 'odata_v3' || $protocol === 'v3') ? 'odata' : 'odatav4';

        if (!empty($tenant)) {
            // Ensure we don't append the tenant if it's already at the end of the base URL
            if (str_ends_with(strtolower($baseUrl), strtolower("/{$tenant}"))) {
                return "{$baseUrl}/{$endpoint}/";
            }
            return "{$baseUrl}/{$tenant}/{$endpoint}/";
        }

        return "{$baseUrl}/{$endpoint}/";
    }

    public function testConnection(array $credentials): ConnectionHealth
    {
        $adapter = $this->resolveAdapter($credentials);
        $runtimeUrl = $this->constructRuntimeUrl($credentials);

        return $adapter->testConnection($credentials, $runtimeUrl);
    }

    public function getDiagnosticInfo(array $credentials): ProviderInfo
    {
        $adapter = $this->resolveAdapter($credentials);
        $runtimeUrl = $this->constructRuntimeUrl($credentials);

        return $adapter->getDiagnosticInfo($credentials, $runtimeUrl);
    }

    public function discoverMetadata(array $credentials): MetadataTree
    {
        $adapter = $this->resolveAdapter($credentials);
        $runtimeUrl = $this->constructRuntimeUrl($credentials);

        return $adapter->discoverMetadata($credentials, $runtimeUrl);
    }

    public function discoverStructure(array $credentials, string $objectName): MetadataNode
    {
        $adapter = $this->resolveAdapter($credentials);
        $runtimeUrl = $this->constructRuntimeUrl($credentials);

        return $adapter->discoverStructure($credentials, $runtimeUrl, $objectName);
    }

    public function createRuntimeConnection(array $credentials): mixed
    {
        $adapter = $this->resolveAdapter($credentials);
        $runtimeUrl = $this->constructRuntimeUrl($credentials);

        return $adapter->createRuntimeConnection($credentials, $runtimeUrl);
    }

    public function getCapabilities(): array
    {
        // For simplicity, delegating this to adapter but you could merge it
        // based on protocol differences if they emerge later.
        $adapter = $this->resolveAdapter([]);
        return $adapter->getCapabilities();
    }
}
