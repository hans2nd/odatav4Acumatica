<?php

namespace App\Jobs;

use App\Models\SyncJob;
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

    public function __construct(
        public string $source = 'manual',
        public ?string $syncJobId = null
    ) {}

    public function handle(): void
    {
        $jobRecord = null;
        if ($this->syncJobId) {
            $jobRecord = SyncJob::find($this->syncJobId);
            if ($jobRecord) {
                $jobRecord->update(['status' => 'running', 'started_at' => now()]);
            }
        }

        $entities = config('acumatica.entities', []);

        // Dispatch master data first, then transactions
        $sorted = collect($entities)->sortBy(fn ($e) => $e['type'] === 'master' ? 0 : 1);

        foreach ($sorted as $key => $entity) {
            Log::info("SyncAllJob: Creating and dispatching sync for [{$key}]");
            
            // Create the child job tracking record
            $childJobRecord = SyncJob::create([
                'entity' => $key,
                'status' => 'pending',
                'progress' => 0,
            ]);

            SyncEntityJob::dispatch($key, [], $this->source, $childJobRecord->id);
        }

        if ($jobRecord) {
            // For SyncAllJob, completion means all child jobs were successfully dispatched
            $jobRecord->update([
                'status' => 'completed',
                'progress' => 100,
                'finished_at' => now(),
            ]);
        }
    }
    
    public function failed(?\Throwable $exception): void
    {
        Log::error("SyncAllJob: Failed", [
            'message' => $exception?->getMessage(),
        ]);

        if ($this->syncJobId) {
            SyncJob::where('id', $this->syncJobId)->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception?->getMessage() ?? 'Unknown error', 0, 15000),
                'finished_at' => now(),
            ]);
        }
    }
}
