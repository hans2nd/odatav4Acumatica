<?php

namespace App\Services;

class QueueWorkerService
{
    /**
     * Check if the queue is configured for async processing.
     * Returns false when using the 'sync' driver (no queue worker needed).
     */
    public function isAsyncQueue(): bool
    {
        return config('queue.default') !== 'sync';
    }

    /**
     * Spawns a background queue worker that processes jobs until the queue is empty.
     * This avoids needing to run a persistent Supervisor/Daemon manually.
     */
    public function startBackgroundWorker(): void
    {
        if (!$this->isAsyncQueue()) {
            return;
        }

        $basePath = base_path();
        $command = "php {$basePath}" . DIRECTORY_SEPARATOR . "artisan queue:work --stop-when-empty --timeout=1800 --memory=1024";

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Detached process in Windows
            pclose(popen("start /B {$command} > NUL 2>&1", "r"));
        } else {
            // Detached process in Linux/Unix
            exec("{$command} > /dev/null 2>&1 &");
        }
    }
}
