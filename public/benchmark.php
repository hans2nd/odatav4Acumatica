<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use App\Services\Metadata\MetadataRepository;
use App\Enums\RefreshReason;
use App\DTO\Metadata\MetadataEntity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

$repo = app(\App\Contracts\Metadata\MetadataRepositoryInterface::class);
$entityKey = 'test_benchmark_entity';

// Ensure config exists
$configService = app(\App\Services\EntityConfigService::class);
if (!$configService->exists($entityKey)) {
    $configService->save($entityKey, [
        'key' => $entityKey,
        'odata_entity' => 'StockItem',
        'table' => 'stock_item',
        'type' => 'master',
        'description' => 'Test Entity',
        'source_connection' => 'acumatica_default'
    ]);
}

$results = [];

// 1. Cold Cache
Cache::forget($entityKey);
DB::table('sync_metadata_definitions')->where('entity_key', $entityKey)->delete();
$t1 = microtime(true);
$repo->getMetadata($entityKey, false, RefreshReason::CACHE_MISS);
$results['cold_cache_ms'] = round((microtime(true) - $t1) * 1000);

// 2. Warm Cache
$t2 = microtime(true);
$repo->getMetadata($entityKey, false, RefreshReason::AUTO);
$results['warm_cache_ms'] = round((microtime(true) - $t2) * 1000);

// 3. Hash Unchanged (Manual refresh, but DB has same hash)
$t3 = microtime(true);
$repo->getMetadata($entityKey, true, RefreshReason::MANUAL);
$results['hash_unchanged_ms'] = round((microtime(true) - $t3) * 1000);

// 4. Hash Changed
// Modify DB hash artificially
DB::table('sync_metadata_definitions')->where('entity_key', $entityKey)->update(['metadata_hash' => 'fake_old_hash']);
$t4 = microtime(true);
$repo->getMetadata($entityKey, true, RefreshReason::MANUAL);
$results['hash_changed_ms'] = round((microtime(true) - $t4) * 1000);

// 5. Queue Refresh
// Dispatch job sync
$t5 = microtime(true);
\App\Jobs\DiscoverEntityMetadataJob::dispatchSync($entityKey);
$results['queue_refresh_ms'] = round((microtime(true) - $t5) * 1000);

header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);
