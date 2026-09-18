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
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class RestApiProvider implements ConnectionProviderInterface
{
    public function getConfigurationSchema(): array
    {
        return [
            new ConfigurationField('base_url', 'Base URL', 'text', true, null, 'https://api.example.com/v1'),
            new ConfigurationField('auth_type', 'Authentication Type', 'select', true, 'bearer', null, null, [
                'none' => 'None',
                'bearer' => 'Bearer Token',
                'basic' => 'Basic Auth',
                'api_key' => 'API Key'
            ]),
            new ConfigurationField('username', 'Username', 'text', false, null, null, 'Required for Basic Auth'),
            new ConfigurationField('password', 'Password', 'password', false, null, null, 'Required for Basic Auth'),
            new ConfigurationField('token', 'Token / API Key', 'password', false, null, null, 'Required for Bearer or API Key Auth'),
            new ConfigurationField('api_key_header', 'API Key Header Name', 'text', false, 'X-API-Key', null, 'Required for API Key Auth'),
        ];
    }

    public function getValidationRules(): array
    {
        return [
            'credentials.base_url'       => 'required|url',
            'credentials.auth_type'      => 'required|in:none,bearer,basic,api_key',
            'credentials.username'       => 'required_if:credentials.auth_type,basic|string',
            'credentials.password'       => 'required_if:credentials.auth_type,basic|string',
            'credentials.token'          => 'required_if:credentials.auth_type,bearer,api_key|string',
            'credentials.api_key_header' => 'required_if:credentials.auth_type,api_key|string',
        ];
    }

    public function testConnection(array $credentials): ConnectionHealth
    {
        $start = microtime(true);
        try {
            $client = $this->createRuntimeConnection($credentials);
            $response = $client->get(''); // Attempt a generic root ping
            
            $elapsed = (int) round((microtime(true) - $start) * 1000);
            
            if ($response->successful() || $response->status() === 404) {
                // 404 is considered reachable for a generic base URL ping
                return new ConnectionHealth(ConnectionHealthStatus::HEALTHY, 'Connected successfully (HTTP ' . $response->status() . ')', $elapsed);
            }
            if (in_array($response->status(), [401, 403])) {
                return new ConnectionHealth(ConnectionHealthStatus::ERROR_AUTH_FAILED, 'Authentication failed: ' . $response->body());
            }

            return new ConnectionHealth(ConnectionHealthStatus::ERROR_UNREACHABLE, 'HTTP Error ' . $response->status() . ': ' . $response->body());
        } catch (Throwable $e) {
            return new ConnectionHealth(ConnectionHealthStatus::ERROR_UNREACHABLE, $e->getMessage());
        }
    }

    public function getDiagnosticInfo(array $credentials): ProviderInfo
    {
        return new ProviderInfo('REST API', 'HTTP Client');
    }

    public function discoverMetadata(array $credentials): MetadataTree
    {
        // For a generic REST API without OpenAPI schema specified, metadata discovery is manual.
        // In a real implementation, we would attempt to fetch /swagger.json or /openapi.json here.
        return new MetadataTree([
            new MetadataNode(name: 'Manual Endpoint Config Required', type: 'endpoint', isQueryable: false)
        ]);
    }

    public function discoverStructure(array $credentials, string $objectName): MetadataNode
    {
        return new MetadataNode(name: $objectName, type: 'endpoint', properties: []);
    }

    public function createRuntimeConnection(array $credentials): PendingRequest
    {
        $baseUrl  = rtrim($credentials['base_url'] ?? '', '/');
        $authType = $credentials['auth_type'] ?? 'none';
        
        $client = Http::baseUrl($baseUrl)->timeout(30)->acceptJson();

        switch ($authType) {
            case 'basic':
                $client->withBasicAuth($credentials['username'] ?? '', $credentials['password'] ?? '');
                break;
            case 'bearer':
                $client->withToken($credentials['token'] ?? '');
                break;
            case 'api_key':
                $header = $credentials['api_key_header'] ?? 'X-API-Key';
                $client->withHeaders([$header => $credentials['token'] ?? '']);
                break;
        }

        return $client;
    }

    public function getCapabilities(): array
    {
        return ['get', 'post', 'put', 'patch', 'delete', 'openapi_discovery'];
    }
}
