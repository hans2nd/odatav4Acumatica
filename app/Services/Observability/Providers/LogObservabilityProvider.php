<?php

namespace App\Services\Observability\Providers;

use App\Services\Observability\Contracts\ObservabilityProviderInterface;
use App\Services\Observability\Models\ObservabilityRecord;
use Illuminate\Support\Facades\Log;

class LogObservabilityProvider implements ObservabilityProviderInterface
{
    public function publish(ObservabilityRecord $record): void
    {
        // Provider ini merekam jejak telemetri ke dalam sistem Log bawaan (misal laravel.log)
        // Di masa depan, ini bisa diganti dengan OpenTelemetryProvider atau yang lainnya.

        $traceId = $record->trace->correlationId->id;
        $jobId = $record->trace->correlationId->jobId;

        $logData = [
            'trace_id' => $traceId,
            'job_id' => $jobId,
            'service' => $record->context->serviceName,
            'duration_secs' => $record->trace->durationSeconds,
            'spans_count' => count($record->trace->spans),
        ];

        Log::info("Observability Trace Completed [{$traceId}]", $logData);

        foreach ($record->trace->spans as $spanName => $span) {
            Log::debug("  -> Span: {$spanName} | Status: {$span->status} | Duration: {$span->durationSeconds}s");
            
            foreach ($span->events as $event) {
                Log::debug("    * Event: {$event->name}");
            }

            foreach ($span->logs as $spanLog) {
                Log::log(strtolower($spanLog->level), "    * Log: {$spanLog->message}", $spanLog->context);
            }
        }
    }
}
