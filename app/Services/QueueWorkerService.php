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
     * Resolve the CLI PHP binary path.
     * Prevents failure on Windows/Laragon when 'php' is not in system PATH.
     */
    protected function getPhpBinary(): string
    {
        // On Windows (e.g. Laragon Apache mod_php or FastCGI), PHP_BINDIR always points to the PHP directory
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && defined('PHP_BINDIR') && PHP_BINDIR) {
            $cliInBinDir = rtrim(PHP_BINDIR, '\\/') . DIRECTORY_SEPARATOR . 'php.exe';
            if (file_exists($cliInBinDir)) {
                return $cliInBinDir;
            }
        }

        $binary = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
        $lowerBinary = strtolower($binary);

        // If web server is running under php-cgi.exe, look for php.exe in the same directory
        if (str_ends_with($lowerBinary, 'php-cgi.exe')) {
            $cli = dirname($binary) . DIRECTORY_SEPARATOR . 'php.exe';
            if (file_exists($cli)) {
                return $cli;
            }
        }

        // If running under Apache mod_php (httpd.exe), fall back to 'php' in PATH
        if (str_ends_with($lowerBinary, 'httpd.exe') || str_ends_with($lowerBinary, 'apache.exe')) {
            return 'php';
        }

        return $binary;
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
        $php = escapeshellarg($this->getPhpBinary());
        $artisan = escapeshellarg($basePath . DIRECTORY_SEPARATOR . 'artisan');
        $command = "{$php} {$artisan} queue:work --stop-when-empty --timeout=3600 --memory=1024";

        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Detached process in Windows:
                // Note: Windows `start` command treats the first quoted string as the window title!
                // Passing `start "" /B` provides an empty title so the quoted PHP binary path is executed properly.
                if (class_exists('COM')) {
                    $WshShell = new \COM("WScript.Shell");
                    $WshShell->Run($command, 0, false);
                } else {
                    pclose(popen("start \"\" /B {$command} > NUL 2>&1", "r"));
                }
            } else {
                // Detached process in Linux/Unix
                if (function_exists('exec')) {
                    @exec("nohup {$command} > /dev/null 2>&1 &");
                } elseif (function_exists('proc_open')) {
                    $descriptors = [
                        0 => ['pipe', 'r'],
                        1 => ['file', '/dev/null', 'w'],
                        2 => ['file', '/dev/null', 'w']
                    ];
                    $proc = @proc_open("nohup {$command} &", $descriptors, $pipes);
                    if (is_resource($proc)) {
                        @proc_close($proc);
                    }
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("QueueWorkerService: Failed to spawn background worker. The server might have disabled exec/popen. Please run the worker manually using Supervisor.", ['error' => $e->getMessage()]);
        }
    }
}
