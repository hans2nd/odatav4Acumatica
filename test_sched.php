<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sched = \App\Models\SyncSchedule::where('entity_key', 'vdatamentahsalesorder')->first();
if ($sched) {
    echo 'Raw: ' . $sched->getRawOriginal('custom_filters') . PHP_EOL;
    echo 'Casted: ' . json_encode($sched->custom_filters) . PHP_EOL;
} else {
    echo 'Not found';
}
