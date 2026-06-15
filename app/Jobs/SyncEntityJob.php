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
        public array $customFilters = []
    ) {}

    public function handle(SyncService $syncService): void
    {
        Log::info("SyncEntityJob: Starting sync for [{$this->entityKey}]", [
            'filters' => $this->customFilters,
        ]);

        try {
            $syncService->syncEntity($this->entityKey, $this->customFilters);
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

        // Ensure progress cache is cleared on failure
        \Illuminate\Support\Facades\Cache::forget("sync_progress_{$this->entityKey}");
    }
}
