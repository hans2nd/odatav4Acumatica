<?php

namespace App\Console\Commands;

use App\Models\Connection;
use App\Models\AiSetting;
use App\Models\SyncEntity;
use App\Services\AI\SchemaCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AiTestSchemaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:test-schema {connection_id? : Optional Connection ID to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely test and inspect AI schema cache and connection diagnostics via CLI';

    /**
     * Execute the console command.
     */
    public function handle(SchemaCacheService $schemaService): int
    {
        $connectionId = $this->argument('connection_id')
            ?: AiSetting::getConfig('analytics_connection_id');

        if (!$connectionId) {
            $connection = Connection::first();
            $connectionId = $connection ? $connection->id : null;
        }

        if (!$connectionId) {
            $this->error('No connection found.');
            return self::FAILURE;
        }

        $this->info("Testing Connection ID: {$connectionId}");

        try {
            $cache = $schemaService->getCache($connectionId);

            $this->line('');
            $this->info('--- RAW SCHEMA CACHE (First 2 Tables) ---');
            $tables = $cache && isset($cache->tables) ? array_slice($cache->tables, 0, 2) : [];
            $this->line(json_encode($tables, JSON_PRETTY_PRINT));

            $this->line('');
            $this->info('--- SYNC ENTITIES ---');
            $syncEntities = SyncEntity::select('id', 'name', 'source_object', 'destination_table')->get();
            $this->line(json_encode($syncEntities, JSON_PRETTY_PRINT));

            $this->line('');
            $this->info('--- SYNC METADATA DEFS ---');
            $metadata = DB::table('sync_metadata_definitions')->select('id', 'entity_key')->get();
            $this->line(json_encode($metadata, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Schema test failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
