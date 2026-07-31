<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\SyncService;
use Illuminate\Support\Facades\Log;

try {
    $syncService = app(SyncService::class);
    // Fetch one entity, for example 'ItemClass' or whatever is available in config
    $entities = config('acumatica.entities');
    $entityKey = array_key_first($entities); // Get the first configured entity
    
    echo "Starting Sync for: {$entityKey}\n";
    
    // Clear log before we start
    file_put_contents(storage_path('logs/laravel.log'), '');
    
    $syncService->syncEntity($entityKey);
    
    echo "Sync completed.\n";
    
    // Output the log so we can see what actually happened
    echo "\n\n--- LOG OUTPUT ---\n";
    echo file_get_contents(storage_path('logs/laravel.log'));
    
} catch (\Exception $e) {
    echo "Failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    echo "\n\n--- LOG OUTPUT ---\n";
    echo file_get_contents(storage_path('logs/laravel.log'));
}
