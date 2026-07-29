<?php

namespace App\Services\BatchProcessing;

use Closure;

class BatchExecutionContext
{
    private bool $isCancelled = false;

    /** @var Closure|null */
    public ?Closure $onBeforeBatch = null;

    /** @var Closure|null */
    public ?Closure $onAfterBatch = null;

    /** @var Closure|null */
    public ?Closure $onBatchFailure = null;

    /** @var Closure|null */
    public ?Closure $onBatchSkipped = null;

    /** @var Closure|null */
    public ?Closure $onExecutionCompleted = null;

    /** 
     * @var Closure|null
     * Format: fn(BatchGroup $group): int (jumlah record berhasil)
     */
    public ?Closure $executorCallback = null;

    /**
     * Meminta penghentian eksekusi yang elegan (graceful stop)
     */
    public function requestCancellation(): void
    {
        $this->isCancelled = true;
    }

    public function isCancelled(): bool
    {
        return $this->isCancelled;
    }
}
