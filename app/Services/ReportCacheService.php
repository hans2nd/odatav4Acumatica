<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class ReportCacheService
{
    protected string $cacheDir;

    public function __construct()
    {
        $this->cacheDir = storage_path('app/reports/cache');
        if (!is_dir($this->cacheDir)) {
            File::makeDirectory($this->cacheDir, 0755, true, true);
        }
    }

    /**
     * Generate a deterministic cache key for a report query with its parameters.
     */
    public function generateCacheKey(string $reportType, string $connectionKey, string $identifier, array $params = [], string $format = 'xlsx'): string
    {
        ksort($params);
        $payload = json_encode([
            'type'       => $reportType,
            'connection' => $connectionKey,
            'identifier' => $identifier,
            'params'     => $params,
            'format'     => strtolower($format),
        ]);

        return 'rep_' . md5($payload);
    }

    /**
     * Get the full file path for a cached report.
     */
    public function getCachePath(string $cacheKey, string $format): string
    {
        $extension = strtolower($format) === 'csv' ? 'csv' : 'xlsx';
        return $this->cacheDir . DIRECTORY_SEPARATOR . "{$cacheKey}.{$extension}";
    }

    /**
     * Check if a valid cached report exists on disk.
     */
    public function getCachedReport(string $cacheKey, string $format): ?array
    {
        $filePath = $this->getCachePath($cacheKey, $format);
        $metadata = Cache::get("report_cache_meta_{$cacheKey}");

        if ($metadata && file_exists($filePath) && filesize($filePath) > 0) {
            return [
                'path'     => $filePath,
                'filename' => $metadata['filename'] ?? basename($filePath),
                'size'     => filesize($filePath),
                'hit'      => true,
            ];
        }

        return null;
    }

    /**
     * Store report metadata and register cache file.
     */
    public function storeCachedReport(string $cacheKey, string $sourcePath, string $filename, string $format, int $ttlSeconds = 3600): array
    {
        $targetPath = $this->getCachePath($cacheKey, $format);

        if ($sourcePath !== $targetPath && file_exists($sourcePath)) {
            File::copy($sourcePath, $targetPath);
        }

        $info = [
            'cache_key' => $cacheKey,
            'filename'  => $filename,
            'format'    => $format,
            'created_at'=> now()->toISOString(),
        ];

        Cache::put("report_cache_meta_{$cacheKey}", $info, $ttlSeconds);

        return [
            'path'     => $targetPath,
            'filename' => $filename,
            'size'     => file_exists($targetPath) ? filesize($targetPath) : 0,
            'hit'      => false,
        ];
    }

    /**
     * Clean up cache files older than given hours.
     */
    public function cleanupOldCache(int $maxAgeHours = 12): int
    {
        $deleted = 0;
        $files = File::files($this->cacheDir);
        $threshold = now()->subHours($maxAgeHours)->timestamp;

        foreach ($files as $file) {
            if ($file->getMTime() < $threshold) {
                File::delete($file->getPathname());
                $deleted++;
            }
        }

        return $deleted;
    }
}
