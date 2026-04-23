<?php

namespace App\Jobs;

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

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        public string $entityKey
    ) {}

    public function handle(SyncService $syncService): void
    {
        Log::info("SyncEntityJob: Starting sync for [{$this->entityKey}]");
        $syncService->syncEntity($this->entityKey);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("SyncEntityJob: Failed for [{$this->entityKey}]", [
            'message' => $exception?->getMessage(),
        ]);
    }
}
