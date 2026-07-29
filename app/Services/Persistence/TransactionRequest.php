<?php

namespace App\Services\Persistence;

use Closure;

class TransactionRequest
{
    /**
     * @param Closure $operation Operasi penutupan yang dibungkus di dalam transaksi
     */
    public function __construct(
        public readonly Closure $operation
    ) {}
}
