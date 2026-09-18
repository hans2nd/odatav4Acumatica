<?php

namespace App\Services\Persistence;

class TransactionResult
{
    /**
     * @param bool $isSuccess Apakah penutupan transaksi (Closure) selesai dikomit
     * @param mixed $payload Nilai kembali dari instruksi (Closure)
     * @param TransactionStatistics $statistics Statistik metrik
     * @param \Throwable|null $error
     */
    public function __construct(
        public readonly bool $isSuccess,
        public readonly mixed $payload,
        public readonly TransactionStatistics $statistics,
        public readonly ?\Throwable $error = null
    ) {}
}
