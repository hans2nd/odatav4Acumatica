<?php

namespace App\Services\Persistence\Contracts;

use App\Services\Persistence\Pipeline\PersistencePipelineExecutionContext;
use Closure;

interface PersistenceStageInterface
{
    /**
     * Memproses konteks dan meneruskannya ke tahapan selanjutnya via Closure
     */
    public function handle(PersistencePipelineExecutionContext $context, Closure $next): PersistencePipelineExecutionContext;
}
