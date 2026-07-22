<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScheduleWorkerService
{
    /**
     * Spawns the scheduler daemon if it is not currently running.
     */
    public function startDaemon(): void
    {
        // We use a cache lock that the daemon will continuously renew.
        // If the lock is missing or expired, it means the daemon is dead or hasn't started.
        $lock = Cache::lock('scheduler_daemon_lock', 10);

        if ($lock->get()) {
            // The daemon is not running (we successfully acquired the lock).
            // Release it immediately so the daemon can acquire it once it starts.
            $lock->release();
            
            Log::info("ScheduleWorkerService: Spawning background scheduler daemon.");

            $basePath = base_path();
            $command = "php {$basePath}" . DIRECTORY_SEPARATOR . "artisan sync:scheduler-daemon";

            try {
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    // Detached process in Windows
                    pclose(popen("start /B {$command} > NUL 2>&1", "r"));
                } else {
                    // Detached process in Linux/Unix
                    exec("{$command} > /dev/null 2>&1 &");
                }
            } catch (\Throwable $e) {
                Log::warning("ScheduleWorkerService: Failed to spawn daemon. The server might have disabled exec/popen. Please run the daemon manually using Supervisor or Cron.", ['error' => $e->getMessage()]);
            }
        }
    }
}
