<?php

namespace App\Services\Persistence\Contracts;

interface SavepointManagerInterface
{
    /**
     * Membuat titik aman (savepoint) menggunakan Persistence Adapter
     */
    public function createSavepoint(string $connectionName, string $savepointName): void;

    /**
     * Membatalkan transaksi ke titik aman (savepoint) tertentu
     */
    public function rollbackToSavepoint(string $connectionName, string $savepointName): void;

    /**
     * Menghapus titik aman (jika didukung)
     */
    public function releaseSavepoint(string $connectionName, string $savepointName): void;
}
