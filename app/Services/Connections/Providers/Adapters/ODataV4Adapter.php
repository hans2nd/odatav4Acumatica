<?php

declare(strict_types=1);

namespace App\Services\Connections\Providers\Adapters;

use App\Contracts\Connections\AcumaticaProtocolInterface;
use App\DTO\Connections\ConnectionHealth;
use App\DTO\Connections\ConnectionHealthStatus;
use App\DTO\Connections\MetadataNode;
use App\DTO\Connections\MetadataTree;
use App\DTO\Connections\ProviderInfo;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class ODataV4Adapter implements AcumaticaProtocolInterface
{
    public function testConnection(array $credentials, string $runtimeUrl): ConnectionHealth
    {
        $start = microtime(true);
        
        try {
            $client = $this->createRuntimeConnection($credentials, $runtimeUrl);
            $response = $client->get('$metadata'); 
            
            $elapsed = (int) round((microtime(true) - $start) * 1000);
            $status = $response->status();
            $body = $response->body();
            
            \Illuminate\Support\Facades\Log::info('Acumatica OData v4 Connection Test', [
                'final_url' => $runtimeUrl . '$metadata',
                'status'    => $status,
                'headers'   => $response->headers(),
                'body'      => $body,
            ]);
            
            if ($response->successful()) {
                return new ConnectionHealth(ConnectionHealthStatus::HEALTHY, 'Connected successfully via OData v4', $elapsed);
            }

            $errorMsg = "HTTP {$status}: " . (empty(trim($body)) ? 'No response body' : substr($body, 0, 500));
            
            if ($status === 401) {
                return new ConnectionHealth(ConnectionHealthStatus::ERROR_AUTH_FAILED, "401 Unauthorized - Check Username/Password. Details: {$errorMsg}");
            }
            if ($status === 403) {
                return new ConnectionHealth(ConnectionHealthStatus::ERROR_AUTH_FAILED, "403 Forbidden - Insufficient permissions. Details: {$errorMsg}");
            }
            if ($status === 404) {
                return new ConnectionHealth(ConnectionHealthStatus::ERROR_UNREACHABLE, "404 Not Found - The endpoint does not exist. Check Base URL and Tenant. Details: {$errorMsg}");
            }
            if ($status >= 500) {
                return new ConnectionHealth(ConnectionHealthStatus::ERROR_UNREACHABLE, "500 Server Error - Acumatica is experiencing issues. Details: {$errorMsg}");
            }

            return new ConnectionHealth(ConnectionHealthStatus::ERROR_UNREACHABLE, "Connection failed. {$errorMsg}");

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $msg = $e->getMessage();
            $detail = 'Network Error: ' . $msg;
            
            if (stripos($msg, 'cURL error 6') !== false || stripos($msg, 'Could not resolve host') !== false) {
                $detail = 'DNS Resolution Failed: ' . $msg;
            } elseif (stripos($msg, 'cURL error 28') !== false || stripos($msg, 'timeout') !== false) {
                $detail = 'Connection Timed Out: ' . $msg;
            } elseif (stripos($msg, 'cURL error 3') !== false || stripos($msg, 'URL using bad/illegal format') !== false) {
                $detail = 'Invalid URL Format: ' . $msg;
            } elseif (stripos($msg, 'cURL error 35') !== false || stripos($msg, 'SSL') !== false || stripos($msg, 'certificate') !== false) {
                $detail = 'SSL/TLS Error: ' . $msg;
            }

            \Illuminate\Support\Facades\Log::error('Acumatica OData v4 connection failed', [
                'url'   => $runtimeUrl . '$metadata',
                'error' => $detail
            ]);
            
            return new ConnectionHealth(ConnectionHealthStatus::ERROR_UNREACHABLE, $detail);
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Acumatica OData v4 unexpected error', [
                'url'   => $runtimeUrl . '$metadata',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new ConnectionHealth(ConnectionHealthStatus::ERROR_UNREACHABLE, 'Unexpected error: ' . $e->getMessage());
        }
    }

    public function getDiagnosticInfo(array $credentials, string $runtimeUrl): ProviderInfo
    {
        return new ProviderInfo('OData v4 (Acumatica)', 'HTTP Client', 'Unknown', 'OData v4');
    }

    public function discoverMetadata(array $credentials, string $runtimeUrl): MetadataTree
    {
        $client = $this->createRuntimeConnection($credentials, $runtimeUrl);
        $response = $client->get(''); 
        
        if (!$response->successful()) {
            throw new \RuntimeException('Failed to fetch OData v4 service document.');
        }

        $data = $response->json();
        $nodes = [];

        if (isset($data['value']) && is_array($data['value'])) {
            foreach ($data['value'] as $entity) {
                if (isset($entity['name'])) {
                    $nodes[] = new MetadataNode(name: $entity['name'], type: 'entity');
                }
            }
        }

        return new MetadataTree($nodes);
    }

    public function discoverStructure(array $credentials, string $runtimeUrl, string $objectName): MetadataNode
    {
        $client = $this->createRuntimeConnection($credentials, $runtimeUrl);
        $response = $client->get($objectName, ['$top' => 1]);
        
        $properties = [];
        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data['value']) && is_array($data['value'][0])) {
                $properties = array_keys($data['value'][0]);
                $properties = array_values(array_filter($properties, fn($p) => !str_starts_with($p, '@')));
            }
        }

        return new MetadataNode(name: $objectName, type: 'entity', properties: $properties);
    }

    public function createRuntimeConnection(array $credentials, string $runtimeUrl): PendingRequest
    {
        $user = $credentials['username'] ?? '';
        $pass = $credentials['password'] ?? '';
        
        return Http::baseUrl($runtimeUrl)
            ->withBasicAuth($user, $pass)
            ->timeout(30)
            ->acceptJson();
    }

    public function getCapabilities(): array
    {
        return ['metadata', 'entity_discovery', 'filter', 'expand', 'select', 'batch'];
    }
}
