<?php

namespace App\Services\Observability\Contracts;

use App\Services\Observability\Models\CorrelationId;
use App\Services\Observability\Models\ExecutionLog;
use App\Services\Observability\Models\ExecutionEvent;
use App\Services\Observability\Models\ObservabilityContext;
use App\Services\Observability\Models\ObservabilityResult;

interface ObservabilityEngineInterface
{
    public function addLifecycle(ObservabilityLifecycleEventsInterface $lifecycle): void;

    /** Memulai Jejak utama sinkronisasi */
    public function startTrace(string $jobId, ?ObservabilityContext $context = null): CorrelationId;

    /** Memulai anak jejak (Span) di dalam Trace */
    public function startSpan(CorrelationId $correlationId, string $spanName): void;

    /** Mengakhiri anak jejak (Span) */
    public function endSpan(CorrelationId $correlationId, string $spanName, string $status = 'COMPLETED'): void;

    /** Menambahkan teks Log pada Span yang aktif */
    public function recordLog(CorrelationId $correlationId, string $spanName, ExecutionLog $log): void;

    /** Menambahkan event spesifik pada Span yang aktif */
    public function recordEvent(CorrelationId $correlationId, string $spanName, ExecutionEvent $event): void;

    /** Mengakhiri Jejak utama dan menyebarkannya ke Providers (Publish) */
    public function endTrace(CorrelationId $correlationId): ObservabilityResult;
}
