<?php

namespace App\Services\Persistence\Contracts;

use App\Services\Persistence\PersistenceCapabilities;
use App\Services\Persistence\PersistenceContext;
use App\Services\Persistence\PersistenceRequest;
use App\Services\Persistence\PersistenceResponse;

interface PersistenceAdapterInterface
{
    /**
     * Mendapatkan nama identifikasi dari adapter (misal: 'mysql').
     */
    public function getName(): string;

    /**
     * Mendapatkan deskripsi spesifik tentang fitur dan batas RDBMS target.
     */
    public function getCapabilities(): PersistenceCapabilities;

    /**
     * Mengamankan nama kolom atau tabel menggunakan karakter kutip sesuai dialek SQL.
     */
    public function quoteIdentifier(string $identifier): string;

    /**
     * Kontrak untuk kelak mengeksekusi penulisan. Di fase ini (5.1), belum menerapkan logika Upsert,
     * sehingga dapat dikembalikan sebagai abstrak atau kerangka kosong.
     */
    public function execute(PersistenceRequest $request, PersistenceContext $context): PersistenceResponse;
}
