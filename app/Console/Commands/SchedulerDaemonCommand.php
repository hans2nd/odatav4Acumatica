<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ScheduleWorkerService;
use App\Services\QueueWorkerService;

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

            // Trigger the native Laravel scheduler
            \Illuminate\Support\Facades\Artisan::call('schedule:run');
            $output = \Illuminate\Support\Facades\Artisan::output();
            
            if (trim($output) && !str_contains($output, 'No scheduled commands are ready to run')) {
                $this->info(trim($output));
                
                // Also trigger the detached queue worker to ensure it processes the job
                app(QueueWorkerService::class)->startBackgroundWorker();
            }

            // Sleep for 60 seconds (standard cron interval)
            sleep(60);
        }
    }
}
