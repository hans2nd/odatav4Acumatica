<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use App\Jobs\SyncEntityJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

try {
    $schedules = DB::table('sync_schedules')->where('is_active', true)->get();

    foreach ($schedules as $schedule) {
        $filters = json_decode($schedule->custom_filters, true) ?? [];

        Schedule::job(new SyncEntityJob($schedule->entity_key, $filters, 'schedule'))
            ->cron($schedule->cron_expression)
            ->when(function () use ($schedule) {
                return (bool) DB::table('sync_schedules')->where('id', $schedule->id)->value('is_active');
            })
            ->withoutOverlapping(120)
            ->name("sync_{$schedule->entity_key}");
    }
} catch (\Exception $e) {
    // If DB is not ready during initial setup, fail gracefully
}
