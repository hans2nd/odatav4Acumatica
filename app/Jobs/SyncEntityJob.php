<?php

namespace App\Jobs;

use App\Models\SyncJob;
use App\Services\AcumaticaODataService;
use App\Services\SyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Log;

class SyncEntityJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Allow up to 30 minutes for very large entities (200K+ rows).
     */
    public int $timeout = 1800;
    
    /**
     * Use exponential backoff for retries.
     */
    public int $tries = 3;
    
    /**
     * Wait 60 seconds before first retry, then 300s (5 mins) to outlast the lock TTL, then 600s.
     */
    public array $backoff = [60, 300, 600];

    /**
     * The persistent identity of this worker instance across retries.
     */
    public string $workerId;

    public function __construct(
        public string $entityKey,
        public array $customFilters = [],
        public string $source = 'manual',
        public ?string $syncJobId = null,
        public ?string $syncModeOverride = null,
        public ?string $watermarkTypeOverride = null,
        public ?string $watermarkColumnOverride = null
    ) {
        $this->workerId = uniqid("worker_{$this->entityKey}_");
    }

    /**
     * Tentukan ID unik untuk job ini agar Laravel tidak menduplikasi antrean.
     */
    public function uniqueId(): string
    {
        return $this->entityKey;
    }

    /**
     * Dynamic timeout based on entity type:
     * - Master data: 600s (10 minutes)
     * - Transaction data: 3600s (60 minutes)
     * - Default: 1800s (30 minutes)
     */
    public function timeout(): int
    {
        try {
            $config = app(\App\Services\EntityConfigService::class)->get($this->entityKey);
            if ($config && isset($config['timeout'])) {
                return (int) $config['timeout'];
            }
            if ($config && ($config['type'] ?? '') === 'transaction') {
                return 3600;
            }
        } catch (\Throwable) {}

        return 1800;
    }

    public function handle(SyncService $syncService): void
    {
        // Remove execution time limit so large entities are not killed mid-execution
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        // Disable query log to prevent memory leaks during long-running batch jobs
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $jobRecord = null;
        if ($this->syncJobId) {
            $jobRecord = SyncJob::find($this->syncJobId);
        }

        // Cache lock has been removed.
        // We now rely on the robust database-based lock in SyncStateService.

        try {
            if ($this->source === 'schedule') {
                $schedule = \Illuminate\Support\Facades\DB::table('sync_schedules')
                    ->where('entity_key', $this->entityKey)
                    ->first();

                if ($schedule && !$schedule->is_active) {
                    Log::info("SyncEntityJob: Aborting scheduled sync for [{$this->entityKey}] because its schedule is inactive.");
                    if ($jobRecord) {
                        $jobRecord->update([
                            'status' => SyncJob::STATUS_COMPLETED, 
                            'finished_at' => now(), 
                            'error_message' => 'Skipped: Schedule was deactivated before execution'
                        ]);
                    }
                    return;
                }
            }

            if ($jobRecord) {
                $jobRecord->update(['status' => SyncJob::STATUS_RUNNING, 'started_at' => now()]);
            }

            Log::info("SyncEntityJob: Starting sync for [{$this->entityKey}]", [
                'filters' => $this->customFilters,
                'job_id' => $this->syncJobId
            ]);

            $syncService->syncEntity($this->entityKey, $this->customFilters, $this->source, $this->syncJobId, $this->workerId, $this->syncModeOverride, $this->watermarkTypeOverride, $this->watermarkColumnOverride);
            
            // Note: SyncService->syncEntity() now guarantees it will update the SyncJob 
            // with the accurate final processed_rows, or it will throw an exception.
            // We no longer blindly mark it as completed here.
        } catch (\Throwable $e) {
            if ($jobRecord) {
                $jobRecord->update([
                    'status' => SyncJob::STATUS_FAILED,
                    'error_message' => mb_substr($e->getMessage(), 0, 15000),
                    'finished_at' => now(),
                ]);
            }
            throw $e; // Rethrow so Laravel registers the queue job failure
        } finally {
            // DB Lock is released in SyncService->syncEntity
            try {
                app(AcumaticaODataService::class)->resetPersistentHttp();
            } catch (\Throwable $e) {
                // Ignore cleanup errors
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("SyncEntityJob: Failed catastrophically for [{$this->entityKey}]", [
            'message' => $exception?->getMessage(),
        ]);

        if ($this->syncJobId) {
            SyncJob::where('id', $this->syncJobId)->update([
                'status' => SyncJob::STATUS_FAILED,
                'error_message' => 'Job failed catastrophically: ' . mb_substr($exception?->getMessage() ?? 'Unknown error', 0, 15000),
                'finished_at' => now(),
            ]);
        }
        

    }
}
