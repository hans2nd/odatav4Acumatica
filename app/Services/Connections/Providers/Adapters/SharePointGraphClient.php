<?php

declare(strict_types=1);

namespace App\Services\Connections\Providers\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * SharePoint Graph API Client
 * 
 * Authenticates via Azure AD (client_credentials flow) and interacts with
 * SharePoint Online through the Microsoft Graph API to list and download
 * Excel/CSV files from document libraries.
 */
class SharePointGraphClient
{
    private const TOKEN_URL = 'https://login.microsoftonline.com/%s/oauth2/v2.0/token';
    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private string $hostname;
    private string $sitePath;

    public function __construct(array $credentials)
    {
        $this->tenantId     = $credentials['tenant_id'] ?? '';
        $this->clientId     = $credentials['client_id'] ?? '';
        $this->clientSecret = $credentials['client_secret'] ?? '';
        $this->hostname     = $credentials['hostname'] ?? '';
        $this->sitePath     = $credentials['site_path'] ?? '';
    }

    /**
     * Obtain an OAuth2 access token using client_credentials grant.
     */
    public function getAccessToken(): string
    {
        $cacheKey = 'sharepoint_token_' . md5($this->tenantId . $this->clientId);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            $tokenUrl = sprintf(self::TOKEN_URL, $this->tenantId);

            $response = Http::asForm()->post($tokenUrl, [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope'         => 'https://graph.microsoft.com/.default',
            ]);

            if (!$response->successful()) {
                $body = $response->json();
                $error = $body['error_description'] ?? $body['error'] ?? 'Unknown error';
                throw new RuntimeException("SharePoint OAuth token request failed: {$error}");
            }

            return $response->json('access_token');
        });
    }

    /**
     * Get the SharePoint site ID from hostname and site path.
     */
    public function getSiteId(): string
    {
        $cacheKey = 'sharepoint_site_id_' . md5($this->hostname . $this->sitePath);

        return Cache::remember($cacheKey, now()->addHours(24), function () {
            $token = $this->getAccessToken();
            $sitePath = ltrim($this->sitePath, '/');

            $url = self::GRAPH_BASE . "/sites/{$this->hostname}:/{$sitePath}";

            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->get($url);

            if (!$response->successful()) {
                $body = $response->json();
                $error = $body['error']['message'] ?? 'Unknown error';
                throw new RuntimeException("Failed to resolve SharePoint site: {$error}");
            }

            return $response->json('id');
        });
    }

    /**
     * Search and list all SharePoint sites in the tenant.
     * Requires Sites.Read.All permission.
     *
     * @return array<int, array{id: string, name: string, webUrl: string, displayName: string, path: string}>
     */
    public function searchSites(): array
    {
        $token = $this->getAccessToken();

        // Search for all sites
        $url = self::GRAPH_BASE . "/sites?search=*";

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->get($url);

        if (!$response->successful()) {
            $body = $response->json();
            $error = $body['error']['message'] ?? 'Unknown error';
            throw new RuntimeException("Failed to list SharePoint sites: {$error}");
        }

        $items = $response->json('value', []);
        $sites = [];

        foreach ($items as $item) {
            $webUrl = $item['webUrl'] ?? '';
            $path = '';
            
            if ($webUrl && str_contains($webUrl, $this->hostname)) {
                $parsed = parse_url($webUrl);
                if (isset($parsed['path'])) {
                    $path = $parsed['path'];
                }
            }
            
            $sites[] = [
                'id' => $item['id'],
                'name' => $item['name'] ?? '',
                'displayName' => $item['displayName'] ?? ($item['name'] ?? 'Unknown'),
                'webUrl' => $webUrl,
                'path' => $path ?: '/',
            ];
        }

        return $sites;
    }

    /**
     * List all document libraries (drives) in the site.
     *
     * @return array<int, array{id: string, name: string, webUrl: string}>
     */
    public function listDrives(): array
    {
        $token  = $this->getAccessToken();
        $siteId = $this->getSiteId();

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->get(self::GRAPH_BASE . "/sites/{$siteId}/drives");

        if (!$response->successful()) {
            throw new RuntimeException('Failed to list SharePoint drives: ' . $response->body());
        }

        return collect($response->json('value', []))
            ->map(fn($d) => [
                'id'     => $d['id'],
                'name'   => $d['name'],
                'webUrl' => $d['webUrl'] ?? '',
            ])
            ->all();
    }

    /**
     * List all files in a specific drive using the Delta API to find them anywhere.
     *
     * @param string $driveId
     * @return array<int, array{id: string, name: string, size: int, lastModified: string, webUrl: string, driveId: string}>
     */
    public function listFiles(string $driveId): array
    {
        $token = $this->getAccessToken();
        
        $url = self::GRAPH_BASE . "/drives/{$driveId}/root/delta";
        $allItems = [];
        $nextLink = $url;

        while ($nextLink) {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->get($nextLink);

            if (!$response->successful()) {
                throw new RuntimeException('Failed to list files: ' . $response->body());
            }

            $data = $response->json();
            $items = $data['value'] ?? [];

            foreach ($items as $item) {
                // Skip folders and deleted items
                if (isset($item['folder']) || isset($item['deleted'])) {
                    continue;
                }

                $name = $item['name'] ?? '';
                
                // Construct the path so the frontend tree-view can see it.
                // The parentReference.path is like "/drive/root:/Folder1/Folder2"
                $parentPath = '';
                if (isset($item['parentReference']['path'])) {
                    $rawPath = $item['parentReference']['path'];
                    $parts = explode(':', $rawPath, 2);
                    if (count($parts) > 1 && $parts[1] !== '') {
                        $parentPath = ltrim($parts[1], '/') . '/';
                    }
                }

                $fullName = $parentPath . $name;

                $allItems[] = [
                    'id'           => $item['id'],
                    'name'         => $fullName,
                    'size'         => $item['size'] ?? 0,
                    'lastModified' => $item['lastModifiedDateTime'] ?? '',
                    'webUrl'       => $item['webUrl'] ?? '',
                    'driveId'      => $driveId,
                ];
            }

            $nextLink = $data['@odata.nextLink'] ?? null;
        }

        return $allItems;
    }

    /**
     * List all files across all drives in the site.
     *
     * @return array<int, array{id: string, name: string, size: int, lastModified: string, webUrl: string, driveId: string, driveName: string}>
     */
    public function listAllFiles(): array
    {
        $drives = $this->listDrives();
        $allFiles = [];

        foreach ($drives as $drive) {
            try {
                $files = $this->listFiles($drive['id']);
                foreach ($files as $file) {
                    $file['driveName'] = $drive['name'];
                    $allFiles[] = $file;
                }
            } catch (Throwable $e) {
                // Non-fatal, skip drive if delta fails or isn't supported
            }
        }

        return $allFiles;
    }

    /**
     * Download a file's content as a stream/string.
     *
     * @param string $driveId
     * @param string $itemId
     * @return string Raw file content
     */
    public function downloadFile(string $driveId, string $itemId): string
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->timeout(120)
            ->get(self::GRAPH_BASE . "/drives/{$driveId}/items/{$itemId}/content");

        if (!$response->successful()) {
            throw new RuntimeException('Failed to download file: ' . $response->body());
        }

        return $response->body();
    }

    /**
     * Download a file to a temporary path and return the path.
     *
     * @param string $driveId
     * @param string $itemId
     * @param string $fileName
     * @return string Temporary file path
     */
    public function downloadToTemp(string $driveId, string $itemId, string $fileName): string
    {
        $content = $this->downloadFile($driveId, $itemId);
        // Sanitize the filename to prevent directory traversal or missing directory errors
        // since $fileName can now contain the full folder path (e.g. "Folder/Sub/File.xlsx")
        $safeFileName = str_replace(['/', '\\'], '_', $fileName);
        
        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sp_' . uniqid() . '_' . $safeFileName;
        file_put_contents($tempPath, $content);

        return $tempPath;
    }

    /**
     * Read an Excel file from SharePoint and return its data as an array.
     *
     * @param string $driveId
     * @param string $itemId
     * @param string $fileName
     * @param string|null $sheetName  Specific sheet name, or null for first sheet
     * @return array{headers: string[], rows: array<int, array<string, mixed>>}
     */
    public function readExcelFile(string $driveId, string $itemId, string $fileName, ?string $sheetName = null): array
    {
        $tempPath = $this->downloadToTemp($driveId, $itemId, $fileName);

        try {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($ext === 'csv') {
                return $this->readCsvFile($tempPath);
            }

            return $this->readXlsxFile($tempPath, $sheetName);
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Read a CSV file and return structured data.
     */
    private function readCsvFile(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new RuntimeException("Cannot open CSV file: {$path}");
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return ['headers' => [], 'rows' => []];
        }

        // Clean BOM and whitespace from headers
        $headers = array_map(function ($h) {
            return trim(preg_replace('/^\x{FEFF}/u', '', $h));
        }, $headers);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $rows[] = array_combine($headers, $row);
            }
        }

        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Read an XLSX file using PhpSpreadsheet and return structured data.
     */
    private function readXlsxFile(string $path, ?string $sheetName = null): array
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        if ($sheetName) {
            $reader->setLoadSheetsOnly([$sheetName]);
        }

        $spreadsheet = $reader->load($path);
        $worksheet = $sheetName
            ? $spreadsheet->getSheetByName($sheetName)
            : $spreadsheet->getActiveSheet();

        if (!$worksheet) {
            throw new RuntimeException("Sheet [{$sheetName}] not found in the Excel file.");
        }

        $data = $worksheet->toArray(null, true, true, true);

        if (empty($data)) {
            return ['headers' => [], 'rows' => []];
        }

        // Auto-detect header row by finding the row with the maximum number of non-empty columns
        // within the first 30 rows. This handles Excel files that have report titles at the top.
        $headerRowIndex = 1;
        $maxColumns = 0;
        
        $limit = min(30, count($data));
        $keys = array_keys($data);
        for ($i = 0; $i < $limit; $i++) {
            $rowNum = $keys[$i];
            $nonEmptyCount = count(array_filter($data[$rowNum], fn($v) => $v !== null && trim((string)$v) !== ''));
            if ($nonEmptyCount > $maxColumns) {
                $maxColumns = $nonEmptyCount;
                $headerRowIndex = $rowNum;
            }
        }

        $headerRow = $data[$headerRowIndex];
        
        \Illuminate\Support\Facades\Log::info('Excel Parse Debug', [
            'total_rows' => count($data),
            'max_columns_detected' => $maxColumns,
            'header_row_index' => $headerRowIndex,
            'header_row_content' => $headerRow
        ]);

        // Remove the header row and all rows above it from the data array
        foreach ($keys as $rowNum) {
            if ($rowNum <= $headerRowIndex) {
                unset($data[$rowNum]);
            }
        }

        $headers = [];
        $headerColMapping = []; // map column letter (A, B, C) to header name
        
        foreach ($headerRow as $colLetter => $v) {
            if ($v !== null && trim((string)$v) !== '') {
                // Ensure unique header names
                $h = trim((string)$v);
                if (in_array($h, $headers)) {
                    $h .= '_' . $colLetter;
                }
                $headers[] = $h;
                $headerColMapping[$colLetter] = $h;
            }
        }

        // Map remaining rows accurately using column letters
        $rows = [];
        foreach ($data as $row) {
            $mapped = [];
            $hasData = false;
            foreach ($headerColMapping as $colLetter => $header) {
                $val = $row[$colLetter] ?? null;
                $mapped[$header] = $val;
                
                if ($val !== null && trim((string)$val) !== '') {
                    $hasData = true;
                }
            }
            
            // Skip completely empty rows
            if ($hasData) {
                $rows[] = $mapped;
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * List sheet names in an Excel file.
     *
     * @return string[]
     */
    public function listSheets(string $driveId, string $itemId, string $fileName): array
    {
        $tempPath = $this->downloadToTemp($driveId, $itemId, $fileName);

        try {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($ext === 'csv') {
                return ['Sheet1'];
            }

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tempPath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($tempPath);

            return $spreadsheet->getSheetNames();
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Recursively list all folders in a drive.
     *
     * @return string[]
     */
    public function listFolders(string $driveId, string $parentPath = ''): array
    {
        $token = $this->getAccessToken();

        if ($parentPath) {
            $parentPath = trim($parentPath, '/');
            $url = self::GRAPH_BASE . "/drives/{$driveId}/root:/{$parentPath}:/children";
        } else {
            $url = self::GRAPH_BASE . "/drives/{$driveId}/root/children";
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->get($url);

        if (!$response->successful()) {
            return [];
        }

        $folders = [];
        foreach ($response->json('value', []) as $item) {
            if (isset($item['folder'])) {
                $folderName = $parentPath ? "{$parentPath}/{$item['name']}" : $item['name'];
                $folders[] = $folderName;
            }
        }

        return $folders;
    }

    /**
     * Ping the SharePoint site to verify connectivity.
     *
     * @return array{success: bool, message: string, site_name: string|null, latency_ms: int}
     */
    public function ping(): array
    {
        $start = microtime(true);

        try {
            $token = $this->getAccessToken();
            $sitePath = ltrim($this->sitePath, '/');
            $url = self::GRAPH_BASE . "/sites/{$this->hostname}:/{$sitePath}";

            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->get($url);

            $latency = (int) round((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                return [
                    'success'   => true,
                    'message'   => 'Connected to SharePoint site successfully',
                    'site_name' => $response->json('displayName'),
                    'site_id'   => $response->json('id'),
                    'web_url'   => $response->json('webUrl'),
                    'latency_ms' => $latency,
                ];
            }

            $error = $response->json('error.message', 'Unknown error');
            $code = $response->json('error.code', 'unknown');

            return [
                'success'    => false,
                'message'    => "SharePoint API error ({$code}): {$error}",
                'site_name'  => null,
                'latency_ms' => $latency,
            ];
        } catch (Throwable $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);

            return [
                'success'    => false,
                'message'    => $e->getMessage(),
                'site_name'  => null,
                'latency_ms' => $latency,
            ];
        }
    }
}
