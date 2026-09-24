<?php

declare(strict_types=1);

namespace App\Services\Connections\Providers;

use App\Contracts\Connections\ConnectionProviderInterface;
use App\DTO\Connections\ConfigurationField;
use App\DTO\Connections\ConnectionError;
use App\DTO\Connections\ConnectionHealth;
use App\DTO\Connections\ConnectionHealthStatus;
use App\DTO\Connections\MetadataNode;
use App\DTO\Connections\MetadataTree;
use App\DTO\Connections\ProviderInfo;
use App\Services\Connections\Providers\Adapters\SharePointGraphClient;
use Throwable;

/**
 * SharePoint Online Connection Provider
 * 
 * Connects to SharePoint Online via Microsoft Graph API using Azure AD
 * client_credentials flow. Lists Excel/CSV files from document libraries
 * and reads them as tabular data that can be synced to a database table.
 */
class SharePointProvider implements ConnectionProviderInterface
{
    public function getConfigurationSchema(): array
    {
        return [
            new ConfigurationField(
                field: 'tenant_id',
                label: 'Azure Tenant ID',
                type: 'text',
                required: true,
                placeholder: 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
                helpText: 'Azure Active Directory Tenant ID (Directory ID)'
            ),
            new ConfigurationField(
                field: 'client_id',
                label: 'Application (Client) ID',
                type: 'text',
                required: true,
                placeholder: 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
                helpText: 'Azure AD App Registration Client ID'
            ),
            new ConfigurationField(
                field: 'client_secret',
                label: 'Client Secret',
                type: 'password',
                required: true,
                placeholder: '••••••••',
                helpText: 'Azure AD App Registration Client Secret value'
            ),
            new ConfigurationField(
                field: 'hostname',
                label: 'SharePoint Hostname',
                type: 'text',
                required: true,
                placeholder: 'yourcompany.sharepoint.com',
                helpText: 'SharePoint Online hostname (e.g. contoso.sharepoint.com)'
            ),
            new ConfigurationField(
                field: 'site_path',
                label: 'Site Path',
                type: 'text',
                required: true,
                default: '/sites/Default',
                placeholder: '/sites/Operational',
                helpText: 'Path to the SharePoint site (e.g. /sites/MySite)'
            ),
        ];
    }

    public function getValidationRules(): array
    {
        return [
            'credentials.tenant_id'     => 'required|string|uuid',
            'credentials.client_id'     => 'required|string|uuid',
            'credentials.client_secret' => 'required|string|min:1',
            'credentials.hostname'      => 'required|string|regex:/^[a-zA-Z0-9\-]+\.sharepoint\.com$/',
            'credentials.site_path'     => 'required|string|starts_with:/',
        ];
    }

    public function testConnection(array $credentials): ConnectionHealth
    {
        $start = microtime(true);

        try {
            $client = new SharePointGraphClient($credentials);
            $result = $client->ping();
            $elapsed = (int) round((microtime(true) - $start) * 1000);

            if ($result['success']) {
                return new ConnectionHealth(
                    status: ConnectionHealthStatus::HEALTHY,
                    message: "Connected to SharePoint site \"{$result['site_name']}\"",
                    pingMs: $result['latency_ms'] ?? $elapsed,
                    checks: [
                        'oauth_token'    => true,
                        'site_resolved'  => true,
                        'graph_api'      => true,
                    ],
                    summary: [
                        'Site Name' => $result['site_name'] ?? 'N/A',
                        'Site URL'  => $result['web_url'] ?? 'N/A',
                        'Hostname'  => $credentials['hostname'] ?? 'N/A',
                    ]
                );
            }

            return new ConnectionHealth(
                status: ConnectionHealthStatus::ERROR_UNREACHABLE,
                message: $result['message'],
                pingMs: $result['latency_ms'] ?? $elapsed,
                checks: [
                    'oauth_token'   => !str_contains($result['message'], 'OAuth'),
                    'site_resolved' => false,
                    'graph_api'     => false,
                ],
                error: new ConnectionError(
                    code: 'SHAREPOINT_CONNECT_FAILED',
                    title: 'SharePoint Connection Failed',
                    message: $result['message'],
                    technical_message: $result['message']
                )
            );
        } catch (Throwable $e) {
            $elapsed = (int) round((microtime(true) - $start) * 1000);

            $isAuthError = str_contains($e->getMessage(), 'AADSTS')
                || str_contains($e->getMessage(), 'unauthorized')
                || str_contains($e->getMessage(), 'invalid_client');

            return new ConnectionHealth(
                status: $isAuthError ? ConnectionHealthStatus::ERROR_AUTH_FAILED : ConnectionHealthStatus::ERROR_UNREACHABLE,
                message: $e->getMessage(),
                pingMs: $elapsed,
                checks: [
                    'oauth_token'   => false,
                    'site_resolved' => false,
                    'graph_api'     => false,
                ],
                error: new ConnectionError(
                    code: $isAuthError ? 'SHAREPOINT_AUTH_FAILED' : 'SHAREPOINT_ERROR',
                    title: $isAuthError ? 'Authentication Failed' : 'Connection Error',
                    message: $isAuthError
                        ? 'Azure AD authentication failed. Check tenant_id, client_id, and client_secret.'
                        : 'Failed to connect to SharePoint.',
                    technical_message: $e->getMessage()
                )
            );
        }
    }

    /**
     * Run full diagnostics including drive listing.
     */
    public function runDiagnostics(array $credentials): ConnectionHealth
    {
        $start = microtime(true);

        try {
            $client = new SharePointGraphClient($credentials);

            // Step 1: OAuth token
            $token = $client->getAccessToken();
            $tokenOk = !empty($token);

            // Step 2: Resolve site
            $siteId = $client->getSiteId();
            $siteOk = !empty($siteId);

            // Step 3: List drives
            $drives = $client->listDrives();
            $drivesOk = count($drives) > 0;

            // Step 4: Count Excel files
            $fileCount = 0;
            $driveNames = [];
            foreach ($drives as $drive) {
                $driveNames[] = $drive['name'];
                try {
                    $files = $client->listFiles($drive['id']);
                    $fileCount += count($files);
                } catch (Throwable $e) {
                    // Non-fatal
                }
            }

            $elapsed = (int) round((microtime(true) - $start) * 1000);

            return new ConnectionHealth(
                status: ConnectionHealthStatus::HEALTHY,
                message: "SharePoint diagnostics passed. Found {$fileCount} file(s) across " . count($drives) . " drive(s).",
                pingMs: $elapsed,
                checks: [
                    'oauth_token'       => $tokenOk,
                    'site_resolved'     => $siteOk,
                    'drives_accessible' => $drivesOk,
                    'files_found'       => $fileCount > 0,
                ],
                metrics: [
                    'latency_ms' => $elapsed,
                ],
                summary: [
                    'Site ID'       => $siteId,
                    'Drives'        => implode(', ', $driveNames),
                    'Drive Count'   => (string) count($drives),
                    'Files'         => (string) $fileCount,
                ]
            );
        } catch (Throwable $e) {
            $elapsed = (int) round((microtime(true) - $start) * 1000);

            return new ConnectionHealth(
                status: ConnectionHealthStatus::ERROR_UNREACHABLE,
                message: 'Diagnostics failed: ' . $e->getMessage(),
                pingMs: $elapsed,
                checks: [
                    'oauth_token'       => false,
                    'site_resolved'     => false,
                    'drives_accessible' => false,
                    'files_found'       => false,
                ],
                error: new ConnectionError(
                    code: 'SHAREPOINT_DIAG_FAILED',
                    title: 'Diagnostics Failed',
                    message: $e->getMessage(),
                    technical_message: $e->getTraceAsString()
                )
            );
        }
    }

    public function getDiagnosticInfo(array $credentials): ProviderInfo
    {
        try {
            $client = new SharePointGraphClient($credentials);
            $result = $client->ping();

            return new ProviderInfo(
                name: 'SharePoint Online',
                driverVersion: 'Microsoft Graph API v1.0',
                serverVersion: $result['site_name'] ?? 'Unknown',
                protocolVersion: 'OAuth 2.0 Client Credentials',
                additionalInfo: [
                    'hostname'  => $credentials['hostname'] ?? '',
                    'site_path' => $credentials['site_path'] ?? '',
                    'site_id'   => $result['site_id'] ?? 'N/A',
                ]
            );
        } catch (Throwable $e) {
            return new ProviderInfo(
                name: 'SharePoint Online',
                driverVersion: 'Microsoft Graph API v1.0',
                serverVersion: 'Error: ' . $e->getMessage(),
                protocolVersion: 'OAuth 2.0 Client Credentials'
            );
        }
    }

    /**
     * Discovers all available Excel/CSV files as metadata nodes.
     * Each file becomes a queryable "object" that can be used as a source entity.
     */
    public function discoverMetadata(array $credentials): MetadataTree
    {
        try {
            $client = new SharePointGraphClient($credentials);
            $files = $client->listAllFiles();
            \Illuminate\Support\Facades\Log::info('SharePoint Files Discovered', ['count' => count($files), 'files' => $files]);

            $nodes = [];
            foreach ($files as $file) {
                $objectName = $this->buildObjectName($file);
                $nodes[] = new MetadataNode(
                    name: $objectName,
                    type: 'file',
                    isQueryable: true,
                    properties: [
                        'file_id'       => $file['id'],
                        'drive_id'      => $file['driveId'],
                        'drive_name'    => $file['driveName'] ?? '',
                        'file_name'     => $file['name'],
                        'size'          => $file['size'],
                        'last_modified' => $file['lastModified'],
                        'web_url'       => $file['webUrl'],
                    ]
                );
            }

            if (empty($nodes)) {
                $nodes[] = new MetadataNode(
                    name: 'No files found',
                    type: 'info',
                    isQueryable: false
                );
            }

            return new MetadataTree($nodes);
        } catch (Throwable $e) {
            return new MetadataTree([
                new MetadataNode(
                    name: 'Error: ' . $e->getMessage(),
                    type: 'error',
                    isQueryable: false
                )
            ]);
        }
    }

    /**
     * Discovers the structure (columns) of a specific Excel file.
     * The objectName format is: "DriveName/FileName" or "DriveName/FileName::SheetName"
     */
    public function discoverStructure(array $credentials, string $objectName): MetadataNode
    {
        try {
            $client = new SharePointGraphClient($credentials);
            $parsed = $this->parseObjectName($objectName, $credentials);

            $driveId  = $parsed['drive_id'];
            $itemId   = $parsed['item_id'];
            $fileName = $parsed['file_name'];
            $sheet    = $parsed['sheet'] ?? null;

            // Read the first few rows to infer column structure (use fast path)
            $data = $client->readExcelFile($driveId, $itemId, $fileName, $sheet, true);

            $properties = [];
            $headerStats = [];
            foreach ($data['headers'] as $header) {
                $headerStats[$header] = [
                    'stringCount' => 0,
                    'numberCount' => 0,
                    'dateCount' => 0,
                    'checkedRows' => 0,
                ];
            }
            
            foreach ($data['rows'] as $row) {
                $allChecked = true;
                foreach ($data['headers'] as $header) {
                    if ($headerStats[$header]['checkedRows'] >= 10) {
                        continue;
                    }
                    $allChecked = false;
                    
                    $cell = $row[$header] ?? ['value' => null, 'type' => 'Empty'];
                    if (!is_array($cell)) {
                        $cell = ['value' => $cell, 'type' => 'Unknown'];
                    }
                    
                    $val = $cell['value'];
                    $type = $cell['type'];
                    
                    if ($val !== null && $val !== '' && $type !== 'Empty') {
                        if ($type === 'String' || $type === 'Error') {
                            $headerStats[$header]['stringCount']++;
                        } elseif ($type === 'Double' || $type === 'Integer') {
                            if (is_numeric($val) && $val > 25000 && $val < 70000) {
                                if (preg_match('/(tanggal|date|tgl|waktu|time|eta|etd)/i', $header)) {
                                    $headerStats[$header]['dateCount']++;
                                } else {
                                    $headerStats[$header]['numberCount']++;
                                }
                            } else {
                                $headerStats[$header]['numberCount']++;
                            }
                        } elseif ($type === 'Boolean') {
                            $headerStats[$header]['stringCount']++;
                        } else {
                            if (is_numeric($val)) {
                                $headerStats[$header]['numberCount']++;
                            } elseif (strtotime((string) $val) !== false && preg_match('/\d{4}[-\/]\d{1,2}[-\/]\d{1,2}/', (string) $val)) {
                                $headerStats[$header]['dateCount']++;
                            } else {
                                $headerStats[$header]['stringCount']++;
                            }
                        }
                        
                        $headerStats[$header]['checkedRows']++;
                    }
                }
                
                if ($allChecked) {
                    break;
                }
            }

            foreach ($data['headers'] as $header) {
                $stats = $headerStats[$header];
                if ($stats['stringCount'] > 0) {
                    $sampleType = 'string';
                } elseif ($stats['dateCount'] > 0) {
                    $sampleType = 'datetime';
                } elseif ($stats['numberCount'] > 0) {
                    $sampleType = 'decimal';
                } else {
                    $sampleType = 'string';
                }

                $properties[] = [
                    'name'     => $header,
                    'type'     => $sampleType,
                    'nullable' => true,
                ];
            }

            return new MetadataNode(
                name: $objectName,
                type: 'file',
                isQueryable: true,
                properties: $properties
            );
        } catch (\Throwable $e) {
            return new MetadataNode(
                name: $objectName,
                type: 'file',
                isQueryable: false,
                properties: ['error' => substr($e->getMessage(), 0, 200)]
            );
        }
    }

    public function listExcelSheets(array $credentials, string $objectName): array
    {
        try {
            $client = new SharePointGraphClient($credentials);
            $parsed = $this->parseObjectName($objectName, $credentials);

            $driveId  = $parsed['drive_id'];
            $itemId   = $parsed['item_id'];
            $fileName = $parsed['file_name'];

            return $client->listSheets($driveId, $itemId, $fileName);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('SharePoint listExcelSheets error', [
                'error' => $e->getMessage(),
                'object' => $objectName
            ]);
            throw new \RuntimeException("Failed to list sheets: " . $e->getMessage());
        }
    }

    /**
     * Creates a runtime SharePointGraphClient instance.
     */
    public function createRuntimeConnection(array $credentials): SharePointGraphClient
    {
        return new SharePointGraphClient($credentials);
    }

    public function getCapabilities(): array
    {
        return ['read', 'schema', 'file_listing', 'excel_parsing'];
    }

    /**
     * Build a unique object name from file metadata.
     * Format: "DriveName/FileName"
     */
    private function buildObjectName(array $file): string
    {
        $driveName = $file['driveName'] ?? 'Default';
        $fileName  = $file['name'] ?? 'unknown';

        return "{$driveName}/{$fileName}";
    }

    /**
     * Parse an object name back into drive/file identifiers.
     * Supports format: "DriveName/FileName" or "DriveName/FileName::SheetName"
     *
     * @return array{drive_id: string, item_id: string, file_name: string, sheet: string|null}
     */
    public function parseObjectName(string $objectName, array $credentials): array
    {
        $sheet = null;

        // Extract sheet name if present
        if (str_contains($objectName, '::')) {
            [$objectName, $sheet] = explode('::', $objectName, 2);
        }

        $parts = explode('/', $objectName, 2);
        $driveName = $parts[0] ?? '';
        $fileName  = $parts[1] ?? $objectName;

        $client = new SharePointGraphClient($credentials);
        $drives = $client->listDrives();

        $driveId = null;
        foreach ($drives as $drive) {
            if (strcasecmp($drive['name'], $driveName) === 0) {
                $driveId = $drive['id'];
                break;
            }
        }

        if (!$driveId && !empty($drives)) {
            $driveId = $drives[0]['id'];
        }

        if (!$driveId) {
            throw new \RuntimeException("No drives found in SharePoint site.");
        }

        // Find the file in the drive
        $itemId = null;
        try {
            $fileData = $client->getFileByPath($driveId, $fileName);
            if ($fileData && isset($fileData['id'])) {
                $itemId = $fileData['id'];
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Direct lookup failed for {$fileName}: " . $e->getMessage());
        }

        if (!$itemId) {
            \Illuminate\Support\Facades\Log::error("File not found in drive (Direct Lookup Failed)", [
                'searched_file' => $fileName,
                'drive' => $driveName,
            ]);
            throw new \RuntimeException("File [{$fileName}] not found in drive [{$driveName}].");
        }

        return [
            'drive_id'  => $driveId,
            'item_id'   => $itemId,
            'file_name' => $fileName,
            'sheet'     => $sheet,
        ];
    }

    /**
     * Download the specified file to a temporary location.
     */
    public function downloadObject(array $credentials, string $objectName): string
    {
        $client = new SharePointGraphClient($credentials);
        $parsed = $this->parseObjectName($objectName, $credentials);

        return $client->downloadToTemp($parsed['drive_id'], $parsed['item_id'], $parsed['file_name']);
    }
}
