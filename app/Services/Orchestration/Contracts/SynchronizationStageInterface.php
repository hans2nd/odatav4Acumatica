<?php

namespace App\Services\Orchestration\Contracts;

use App\Services\Orchestration\SynchronizationExecutionContext;
use Closure;

interface SynchronizationStageInterface
{
    /**
     * Memproses konteks di tahap ini, lalu melanjutkannya ke tahap berikutnya via Closure.
     */
    public function process(SynchronizationExecutionContext $context, Closure $next): SynchronizationExecutionContext;
}
