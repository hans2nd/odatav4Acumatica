<?php

namespace App\Services\Orchestration;

class SynchronizationExecutionContext
{
    /** @var SynchronizationExecutionMetrics Metrik dari awal hingga akhir sinkronisasi */
    public SynchronizationExecutionMetrics $metrics;

    /** @var SynchronizationSummary Ringkasan dari berbagai stage */
    public SynchronizationSummary $summary;

    /**
     * @param SynchronizationExecutionRequest $request Instruksi awal yang dikirimkan pemanggil
     */
    public function __construct(
        public readonly SynchronizationExecutionRequest $request
    ) {
        $this->metrics = new SynchronizationExecutionMetrics();
        $this->summary = new SynchronizationSummary();
    }
}
