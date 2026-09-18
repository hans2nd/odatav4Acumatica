<?php

namespace App\Services\Persistence\Pipeline;

class PersistencePipelineExecutionContext
{
    /**
     * @var array Kumpulan record yang selamat dan akan di-upsert
     */
    public array $upsertRecords;

    /**
     * @var array Kumpulan record yang akan dihapus
     */
    public array $deleteRecords;

    public PersistencePipelineMetrics $metrics;

    public function __construct(
        public readonly PersistencePipelineContext $originalContext
    ) {
        $this->upsertRecords = $originalContext->upsertRecords;
        $this->deleteRecords = $originalContext->deleteRecords;
        $this->metrics = new PersistencePipelineMetrics();
    }
}
