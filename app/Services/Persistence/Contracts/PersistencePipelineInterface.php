<?php

namespace App\Services\Persistence\Contracts;

use App\Services\Persistence\Pipeline\PersistencePipelineContext;
use App\Services\Persistence\Pipeline\PersistencePipelineResult;

interface PersistencePipelineInterface
{
    public function addLifecycle(PersistenceLifecycleEventsInterface $lifecycle): void;
    
    /**
     * Memulai pemrosesan berjenjang dari tahap awal hingga akhir
     */
    public function process(PersistencePipelineContext $context): PersistencePipelineResult;
}
