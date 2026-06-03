<?php

namespace App\Services;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AcumaticaODataService
{
    private string $baseUrl;
    private string $tenant;
    private string $username;
    private string $password;
    private bool $verifySsl;
    private string $odataAuth;
    private bool $includeTenantInODataUrl;
    private int $pageSize;
    private int $timeout;
    private ?CookieJar $cookieJar = null;
    private bool $authenticated = false;

    public function __construct()
    {
        $this->baseUrl   = rtrim(config('acumatica.url'), '/');
        $this->tenant    = config('acumatica.tenant');
        $this->username  = config('acumatica.username');
        $this->password  = config('acumatica.password');
        $this->verifySsl = config('acumatica.verify_ssl', false);
        $this->odataAuth = config('acumatica.odata_auth', 'basic');
        $this->includeTenantInODataUrl = (bool) config('acumatica.odata_include_tenant', false);
        $this->pageSize  = config('acumatica.page_size', 500);
        $this->timeout   = config('acumatica.timeout', 300);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    /**
     * Login to Acumatica and store session cookies.
     */
    public function login(): bool
    {
        $this->cookieJar = new CookieJar();

        try {
            $response = Http::withOptions([
                'cookies' => $this->cookieJar,
                'verify'  => $this->verifySsl,
                'timeout' => $this->timeout,
            ])->post($this->baseUrl . '/entity/auth/login', [
                'name'     => $this->username,
                'password' => $this->password,
                'company'  => $this->tenant,
            ]);

            if ($response->status() === 204 || $response->successful()) {
                $this->authenticated = true;
                Log::info('Acumatica: Login successful.');
                return true;
            }

            Log::error('Acumatica: Login failed.', [
                'status'   => $response->status(),
                'response' => $response->body(),
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Acumatica: Login exception.', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Logout from Acumatica.
     */
    public function logout(): void
    {
        if (! $this->authenticated || ! $this->cookieJar) {
            return;
        }

        try {
            Http::withOptions([
                'cookies' => $this->cookieJar,
                'verify'  => $this->verifySsl,
                'timeout' => 30,
            ])->post($this->baseUrl . '/entity/auth/logout');

            Log::info('Acumatica: Logout successful.');

        } catch (\Exception $e) {
            Log::warning('Acumatica: Logout failed.', ['message' => $e->getMessage()]);
        } finally {
            $this->authenticated = false;
            $this->cookieJar = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | OData Data Fetching
    |--------------------------------------------------------------------------
    */

    /**
     * Fetch a single page of data from an OData entity.
     */
    public function fetchPage(string $odataEntity, int $top = 500, int $skip = 0, string $filter = ''): array
    {
        if ($this->usesSessionAuth()) {
            $this->ensureAuthenticated();
        }

        $pathParts = [
            $this->baseUrl,
            config('acumatica.odata_path', 'odatav4'),
        ];

        if ($this->includeTenantInODataUrl) {
            $pathParts[] = rawurlencode($this->tenant);
        }

        $pathParts[] = $odataEntity;
        
        $isV4 = config('acumatica.odata_path', 'odatav4') === 'odatav4';
        $queryCount = $isV4 ? '&$count=true' : '&$inlinecount=allpages&$format=json';
        
        $url = implode('/', $pathParts) . sprintf('?$top=%d&$skip=%d%s', $top, $skip, $queryCount);
        
        if (!empty($filter)) {
            $url .= '&$filter=' . rawurlencode($filter);
        }

        return $this->fetchPageByUrl($url, $odataEntity);
    }

    /**
     * Fetch a single page of data using a direct URL.
     */
    public function fetchPageByUrl(string $url, string $odataEntity = 'unknown'): array
    {
        if ($this->usesSessionAuth()) {
            $this->ensureAuthenticated();
        }

        Log::debug("Acumatica OData: Fetching URL", ['url' => $url]);

        $request = Http::withOptions([
            'verify'  => $this->verifySsl,
            'timeout' => $this->timeout,
        ])->acceptJson();

        if ($this->usesSessionAuth()) {
            $request = $request->withOptions(['cookies' => $this->cookieJar]);
        } else {
            $request = $request->withBasicAuth($this->username, $this->password);
        }

        $response = $request->get($url);

        if (! $response->successful()) {
            $body = $response->body();
            Log::error("Acumatica OData: Failed to fetch {$odataEntity}", [
                'status' => $response->status(),
                'body'   => mb_substr($body, 0, 1000),
                'url'    => $url,
            ]);
            $hint = $response->status() === 404
                ? ' Entity not found in OData. Check that the Generic Inquiry/entity name is exposed for OData.'
                : '';

            throw new \RuntimeException(
                "OData fetch failed for {$odataEntity}: HTTP {$response->status()} - " . mb_substr($body, 0, 500) . $hint
            );
        }

        return $response->json();
    }

    /**
     * Fetch ALL records from an OData entity using pagination.
     *
     * @param string $odataEntity
     * @param string $filter
     * @param callable|null $onProgress function($current, $total)
     * @param callable|null $onPageFetched function(array $recordsChunk)
     * @return array  Flat array of records (only if $onPageFetched is NOT provided).
     */
    public function fetchAllRecords(string $odataEntity, string $filter = '', ?callable $onProgress = null, ?callable $onPageFetched = null): array
    {
        $allRecords = [];
        $skip = 0;
        $hasMore = true;
        $totalFetched = 0;
        $nextLink = null;

        while ($hasMore) {
            if ($nextLink) {
                // Fetch using nextLink directly if provided by the previous response
                $result = $this->fetchPageByUrl($nextLink, $odataEntity);
            } else {
                $result = $this->fetchPage($odataEntity, $this->pageSize, $skip, $filter);
            }

            $records = $result['d']['results'] ?? $result['value'] ?? [];
            $recordsCount = count($records);
            $totalFetched += $recordsCount;
            
            $total = null;
            if (isset($result['@odata.count'])) {
                $total = (int) $result['@odata.count'];
            } elseif (isset($result['d']['__count'])) {
                $total = (int) $result['d']['__count'];
            }

            if ($onProgress) {
                $onProgress($totalFetched, $total);
            }
            
            if ($onPageFetched) {
                if ($recordsCount > 0) {
                    $onPageFetched($records);
                }
            } else {
                $allRecords = array_merge($allRecords, $records);
            }

            $nextLink = $result['@odata.nextLink'] ?? $result['d']['__next'] ?? null;

            if ($nextLink) {
                $hasMore = true;
            } elseif ($recordsCount === $this->pageSize) {
                // Fallback: manually paginate with skip if no nextLink was provided but we got a full page
                $skip += $this->pageSize;
                $hasMore = true;
            } else {
                $hasMore = false;
            }

            Log::debug("Acumatica OData: Fetched {$recordsCount} records from {$odataEntity}, total so far: {$totalFetched}");
        }

        return $allRecords;
    }

    /*
    |--------------------------------------------------------------------------
    | Connection Test
    |--------------------------------------------------------------------------
    */

    /**
     * Test connectivity to the Acumatica instance.
     */
    public function testConnection(): array
    {
        $start = microtime(true);

        try {
            $loginOk = $this->login();

            if ($loginOk) {
                $this->logout();
            }

            $elapsed = round((microtime(true) - $start) * 1000);

            return [
                'connected' => $loginOk,
                'ping_ms'   => $elapsed,
                'message'   => $loginOk ? 'Connected' : 'Login failed',
            ];

        } catch (\Exception $e) {
            $elapsed = round((microtime(true) - $start) * 1000);
            return [
                'connected' => false,
                'ping_ms'   => $elapsed,
                'message'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Test OData v4 connectivity directly.
     */
    public function testODataConnection(): array
    {
        $start = microtime(true);

        try {
            if ($this->usesSessionAuth()) {
                if (!$this->login()) {
                    return ['connected' => false, 'ping_ms' => 0, 'message' => 'Login authentication failed'];
                }
            }

            $pathParts = [
                $this->baseUrl,
                config('acumatica.odata_path', 'odatav4'),
            ];

            if ($this->includeTenantInODataUrl) {
                $pathParts[] = rawurlencode($this->tenant);
            }

            $url = implode('/', $pathParts);

            $request = Http::withOptions([
                'verify'  => $this->verifySsl,
                'timeout' => 30,
            ])->acceptJson();

            if ($this->usesSessionAuth()) {
                $request = $request->withOptions(['cookies' => $this->cookieJar]);
            } else {
                $request = $request->withBasicAuth($this->username, $this->password);
            }

            $response = $request->get($url);

            if ($this->usesSessionAuth()) {
                $this->logout();
            }

            $elapsed = round((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                return [
                    'connected' => true,
                    'ping_ms'   => $elapsed,
                    'message'   => 'Connected',
                ];
            }

            return [
                'connected' => false,
                'ping_ms'   => $elapsed,
                'message'   => 'HTTP ' . $response->status() . ' - ' . mb_substr($response->body(), 0, 200),
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'ping_ms'   => 0,
                'message'   => $e->getMessage(),
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function ensureAuthenticated(): void
    {
        if (! $this->authenticated || ! $this->cookieJar) {
            $ok = $this->login();
            if (! $ok) {
                throw new \RuntimeException('Unable to authenticate with Acumatica.');
            }
        }
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function usesSessionAuth(): bool
    {
        return strtolower($this->odataAuth) === 'session';
    }
}
