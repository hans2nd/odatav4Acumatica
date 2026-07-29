<?php

namespace App\Services;

use App\Models\SyncBottleneck;
use App\Models\SyncLog;
use Illuminate\Support\Facades\Log;

class BottleneckDetector
{
    private array $thresholds = [
        'provider_fetch' => 5000, // 5 seconds per chunk
        'db_write' => 3000,       // 3 seconds per chunk
        'memory_mb' => 200,       // 200 MB
    ];

    public function record(SyncLog $log, string $pipeline, string $stage, float $durationMs, float $thresholdMs, string $severity, string $recommendation)
    {
        try {
            SyncBottleneck::create([
                'sync_log_id' => $log->id,
                'entity_key' => $log->entity_key,
                'pipeline' => $pipeline,
                'stage' => $stage,
                'duration_ms' => $durationMs,
                'threshold_ms' => $thresholdMs,
                'severity' => $severity,
                'recommendation' => $recommendation,
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to record bottleneck: " . $e->getMessage());
        }
    }

    public function analyzeChunk(SyncLog $log, float $fetchTimeMs, float $writeTimeMs, float $memoryMb)
    {
        if ($fetchTimeMs > $this->thresholds['provider_fetch']) {
            $this->record($log, 'provider', 'fetch_chunk', $fetchTimeMs, $this->thresholds['provider_fetch'], 'warning', 'Consider reducing $top chunk size or optimize OData view on Acumatica.');
        }

        if ($writeTimeMs > $this->thresholds['db_write']) {
            $this->record($log, 'persistence', 'upsert_chunk', $writeTimeMs, $this->thresholds['db_write'], 'warning', 'Consider optimizing database indexes or reducing chunk size.');
        }

        if ($memoryMb > $this->thresholds['memory_mb']) {
            $this->record($log, 'processing', 'memory_usage', $memoryMb, $this->thresholds['memory_mb'], 'critical', 'High memory usage detected. Potential memory leak in chunk processing.');
        }
    }
}
