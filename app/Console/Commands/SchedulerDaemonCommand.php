<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SchedulerDaemonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:scheduler-daemon';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs continuously to trigger scheduled syncs.';

    public function handle()
    {
        $this->info("Starting Scheduler Daemon...");
        \Illuminate\Support\Facades\Log::info("SchedulerDaemon: Started in background.");

        // Loop indefinitely
        while (true) {
            // Keep the lock alive so WebCronMiddleware knows we are running.
            // Using 60 seconds TTL.
            \Illuminate\Support\Facades\Cache::lock('scheduler_daemon_lock', 60)->get();

            $now = now();
            
            // Get all active schedules that are due
            $dueSchedules = \App\Models\SyncSchedule::where('is_active', true)
                ->where(function ($query) use ($now) {
                    $query->whereNull('next_run_at')
                          ->orWhere('next_run_at', '<=', $now);
                })
                ->get();

            foreach ($dueSchedules as $schedule) {
                $this->info("Triggering schedule for: {$schedule->entity_key}");
                
                // Set cache so UI knows it's starting
                \Illuminate\Support\Facades\Cache::put("sync_progress_{$schedule->entity_key}", [
                    'current' => 0,
                    'total'   => 0,
                    'status'  => 'starting',
                ], 3600);

                // Fetch saved custom filters
                $filters = $schedule->custom_filters ?? [];

                // Dispatch the job
                \App\Jobs\SyncEntityJob::dispatch($schedule->entity_key, $filters, 'schedule');

                // Update schedule timings
                $schedule->last_run_at = $now;
                
                $nextRun = null;
                if ($schedule->frequency) {
                    if ($schedule->frequency === 'minute') {
                        $nextRun = now()->addMinutes($schedule->interval ?? 1);
                    } elseif ($schedule->frequency === 'hour') {
                        $nextRun = now()->addHours($schedule->interval ?? 1);
                    } elseif ($schedule->time) {
                        $nextRun = \Carbon\Carbon::parse($schedule->time);
                        if ($schedule->frequency === 'daily') {
                            if ($nextRun->isPast()) $nextRun->addDay();
                        } elseif ($schedule->frequency === 'weekly') {
                            $dayOfWeek = (int) ($schedule->day_of_week ?? 1);
                            if ($nextRun->dayOfWeekIso !== $dayOfWeek || $nextRun->isPast()) {
                                $nextRun->next($dayOfWeek);
                            }
                        } elseif ($schedule->frequency === 'monthly') {
                            $dayOfMonth = (int) ($schedule->day_of_month ?? 1);
                            $nextRun->setDate($nextRun->year, $nextRun->month, min($dayOfMonth, $nextRun->daysInMonth));
                            if ($nextRun->isPast()) {
                                $nextRun->addMonth();
                                $nextRun->day = min($dayOfMonth, $nextRun->daysInMonth);
                            }
                        }
                    }
                }
                
                $schedule->next_run_at = $nextRun;
                $schedule->save();
                
                // Also trigger the detached queue worker to ensure it processes the job
                app(\App\Services\QueueWorkerService::class)->startBackgroundWorker();
            }

            // Sleep for 30 seconds before next check
            // This ensures we check frequently enough but don't burn CPU
            sleep(30);
        }
    }
}
