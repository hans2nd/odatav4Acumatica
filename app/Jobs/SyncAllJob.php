<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAllJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct() {}

    public function handle(): void
    {
        $entities = config('acumatica.entities', []);

        // Dispatch master data first, then transactions
        $sorted = collect($entities)->sortBy(fn ($e) => $e['type'] === 'master' ? 0 : 1);

        foreach ($sorted as $key => $entity) {
            Log::info("SyncAllJob: Dispatching sync for [{$key}]");
            SyncEntityJob::dispatch($key);
        }
    }
}
