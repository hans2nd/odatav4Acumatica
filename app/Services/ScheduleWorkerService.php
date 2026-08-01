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
            // Do NOT release it immediately. Let the daemon's own lock attempt overwrite/renew it.
            // If the daemon fails to start, this lock will expire naturally in 10 seconds.
            
            Log::info("ScheduleWorkerService: Spawning background scheduler daemon.");

            $basePath = base_path();
            $command = "php {$basePath}" . DIRECTORY_SEPARATOR . "artisan sync:scheduler-daemon";

            try {
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    // Detached process in Windows
                if (class_exists('COM')) {
                    $WshShell = new \COM("WScript.Shell");
                    $WshShell->Run("cmd /c {$command}", 0, false);
                } else {
                    pclose(popen("start /B " . $command, "r"));
                }
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
