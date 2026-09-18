<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Connection;
use App\Services\AI\SchemaCacheService;
use Illuminate\Support\Facades\Log;

class RefreshSchemaCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $connectionId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $connectionId)
    {
        $this->connectionId = $connectionId;
    }

    /**
     * Execute the job.
     */
    public function handle(SchemaCacheService $schemaCacheService): void
    {
        try {
            $connection = Connection::find($this->connectionId);
            if (!$connection) {
                Log::error("RefreshSchemaCacheJob: Connection {$this->connectionId} not found.");
                return;
            }

            if (!$connection->is_analytics_source) {
                Log::warning("RefreshSchemaCacheJob: Connection {$this->connectionId} is not an analytics source. Skipping schema cache.");
                return;
            }

            Log::info("RefreshSchemaCacheJob: Starting schema cache refresh for connection {$this->connectionId}.");
            $schemaCacheService->cacheSchema($connection);
            Log::info("RefreshSchemaCacheJob: Completed schema cache refresh for connection {$this->connectionId}.");

        } catch (\Exception $e) {
            Log::error("RefreshSchemaCacheJob: Failed to refresh schema cache for connection {$this->connectionId}. Error: " . $e->getMessage());
        }
    }
}
