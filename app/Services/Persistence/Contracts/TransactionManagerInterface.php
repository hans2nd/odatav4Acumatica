<?php

namespace App\Services\Persistence\Contracts;

use App\Services\Persistence\TransactionContext;
use App\Services\Persistence\TransactionRequest;
use App\Services\Persistence\TransactionResult;

interface TransactionManagerInterface
{
    /**
     * Mendaftarkan pendengar siklus hidup (Observer)
     */
    public function addLifecycle(TransactionLifecycleInterface $lifecycle): void;

    /**
     * Mengeksekusi instruksi di dalam naungan transaksi database
     */
    public function execute(TransactionRequest $request, TransactionContext $context): TransactionResult;
}
