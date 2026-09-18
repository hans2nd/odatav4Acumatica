<?php

namespace App\Services\Reliability\Pipeline;

class ReliabilityPipelineExecutionContext
{
    /** @var ReliabilityPipelineMetrics Metrik yang diakumulasikan sepanjang stages */
    public ReliabilityPipelineMetrics $metrics;

    /** @var RecoveryExecutionSummary Rangkuman eksekusi tahap-demi-tahap */
    public RecoveryExecutionSummary $summary;

    public function __construct(
        public readonly ReliabilityPipelineContext $originalContext
    ) {
        $this->metrics = new ReliabilityPipelineMetrics();
        $this->summary = new RecoveryExecutionSummary();
    }
}
