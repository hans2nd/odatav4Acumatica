<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$logs = \App\Models\SyncLog::orderByDesc('id')->limit(5)->get()->toArray();
$jobs = \App\Models\SyncJob::orderByDesc('id')->limit(5)->get()->toArray();

$laravelLogs = [];
if (file_exists(storage_path('logs/laravel.log'))) {
    $file = file(storage_path('logs/laravel.log'));
    $laravelLogs = array_slice($file, -100);
}

$schedules = \Illuminate\Support\Facades\DB::table('sync_schedules')->get()->toArray();

$output = json_encode([
    'sync_logs' => $logs,
    'sync_jobs' => $jobs,
    'sync_schedules' => $schedules,
    'laravel_log' => $laravelLogs
], JSON_PRETTY_PRINT);

file_put_contents(storage_path('logs/dev_debug.json'), $output);
echo "Done";
