<?php

namespace App\Jobs;

use App\Services\AcumaticaODataService;
use App\Services\SyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncEntityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Allow up to 30 minutes for very large entities (200K+ rows).
     */
    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(
        public string $entityKey,
        public array $customFilters = [],
        public string $source = 'manual'
    ) {}

    /**
     * Get the middleware the job should pass through.
     * Prevents multiple concurrent syncs for the same entity.
     */
    public function middleware()
    {
        return [
            // Do not release back to queue; discard overlapping jobs to prevent queue pile-up.
            (new WithoutOverlapping($this->entityKey))->dontRelease()
        ];
    }

    public function handle(SyncService $syncService): void
    {
        Log::info("SyncEntityJob: Starting sync for [{$this->entityKey}]", [
            'filters' => $this->customFilters,
        ]);

        try {
            $syncService->syncEntity($this->entityKey, $this->customFilters, $this->source);
        } finally {
            // Release persistent HTTP resources after sync completes
            try {
                app(AcumaticaODataService::class)->resetPersistentHttp();
            } catch (\Throwable $e) {
                // Ignore cleanup errors
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("SyncEntityJob: Failed for [{$this->entityKey}]", [
            'message' => $exception?->getMessage(),
        ]);

        // Find the running log and mark it as failed
        $runningLog = \App\Models\SyncLog::where('entity_key', $this->entityKey)
            ->where('status', 'running')
            ->orderBy('id', 'desc')
            ->first();

        if ($runningLog) {
            $runningLog->update([
                'status' => 'failed',
                'error_message' => 'Job failed catastrophically: ' . mb_substr($exception?->getMessage() ?? 'Unknown error', 0, 15000),
                'completed_at' => now(),
            ]);
        }

        // Ensure progress cache is cleared on failure
        \Illuminate\Support\Facades\Cache::forget("sync_progress_{$this->entityKey}");
    }
}
