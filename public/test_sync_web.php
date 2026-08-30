<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\SyncService;
use Illuminate\Support\Facades\Log;

try {
    $syncService = app(SyncService::class);
    $entities = app(\App\Services\EntityConfigService::class)->getAll();
    $entityKey = array_key_first($entities); 
    
    echo "<pre>";
    echo "Starting Sync for: {$entityKey}\n";
    
    file_put_contents(storage_path('logs/laravel.log'), '');
    
    $syncService->syncEntity($entityKey);
    
    echo "Sync completed.\n";
    
    echo "\n\n--- LOG OUTPUT ---\n";
    echo file_get_contents(storage_path('logs/laravel.log'));
    echo "</pre>";
} catch (\Exception $e) {
    echo "<pre>";
    echo "Failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    echo "\n\n--- LOG OUTPUT ---\n";
    echo file_get_contents(storage_path('logs/laravel.log'));
    echo "</pre>";
}
