<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Models\Connection;
use App\Services\AI\SchemaCacheService;
use App\Services\AI\SemanticCandidateDiscoveryService;

header('Content-Type: text/plain');

$connectionId = \App\Models\AiSetting::getConfig('analytics_connection_id');
if (!$connectionId) {
    $connection = Connection::first();
    $connectionId = $connection ? $connection->id : null;
}

if (!$connectionId) {
    echo "No connection found.\n";
    exit;
}

echo "Testing Connection ID: $connectionId\n";

$schemaService = app(SchemaCacheService::class);
$cache = $schemaService->getCache($connectionId);

echo "\n--- RAW SCHEMA CACHE (First 2 Tables) ---\n";
echo json_encode(array_slice($cache->tables, 0, 2), JSON_PRETTY_PRINT) . "\n";

$syncEntities = \App\Models\SyncEntity::select('id', 'name', 'source_object', 'destination_table')->get();
echo "\n--- SYNC ENTITIES ---\n";
echo json_encode($syncEntities, JSON_PRETTY_PRINT) . "\n";

$metadata = DB::table('sync_metadata_definitions')->select('id', 'entity_key')->get();
echo "\n--- SYNC METADATA DEFS ---\n";
echo json_encode($metadata, JSON_PRETTY_PRINT) . "\n";

