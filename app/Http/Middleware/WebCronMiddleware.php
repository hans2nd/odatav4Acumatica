<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ScheduleWorkerService;

class WebCronMiddleware
{
    /**
     * Handle an incoming request.
     * We don't block the request here.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     * This ensures zero overhead to the page load time.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Ensure the persistent scheduler daemon is running in the background.
        // The ScheduleWorkerService will check the lock and spawn it if it's dead.
        try {
            app(ScheduleWorkerService::class)->startDaemon();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("WebCronMiddleware Error: " . $e->getMessage());
        }
    }
}
