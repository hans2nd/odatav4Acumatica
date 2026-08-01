<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$entity = 'vdatamentahsalesorder';
$customFilters = [
    ['field' => 'OrderDate', 'operator' => 'ge', 'value' => '2026-06-30T00:00:00.000Z']
];

\App\Models\SyncSchedule::updateOrCreate(
    ['entity_key' => $entity],
    ['custom_filters' => $customFilters]
);

$sched = \App\Models\SyncSchedule::where('entity_key', $entity)->first();
echo "Raw in DB: " . $sched->getRawOriginal('custom_filters') . PHP_EOL;
echo "Casted by Eloquent: " . json_encode($sched->custom_filters) . PHP_EOL;
