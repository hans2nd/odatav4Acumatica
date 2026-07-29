<?php

namespace App\Services\Persistence;

class TransactionContext
{
    /**
     * @param string $connectionName Nama koneksi RDBMS
     * @param string|null $isolationLevel Referensi TransactionIsolationLevel
     * @param int|null $timeoutSeconds Batas waktu transaksi (jika didukung)
     */
    public function __construct(
        public readonly string $connectionName,
        public readonly ?string $isolationLevel = null,
        public readonly ?int $timeoutSeconds = null
    ) {}
}
